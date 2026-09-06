<?php
/**
 * Handlers/Upload.php
 */

final class UploadHandler
{
    private const ALLOWED_EXT = ['py', 'js', 'zip'];

    private static function userDir(int $userId): string
    {
        $d = Config::$uploadDir . '/' . $userId;
        if (!is_dir($d)) {
            mkdir($d, 0777, true);
        }
        return $d;
    }

    /** Triggered by the "Upload File" menu button. */
    public static function entry(TelegramApi $api, array $callbackQuery): void
    {
        $from = $callbackQuery['from'];
        $userId = (int) $from['id'];
        $chatId = $callbackQuery['message']['chat']['id'];

        $api->answerCallbackQuery($callbackQuery['id']);

        if (!Storage::isBotEnabled() && !in_array($userId, Config::$adminIds, true)) {
            $api->sendMessage($chatId, '🚧 The bot is currently under maintenance.');
            return;
        }

        $projects = Storage::getUserProjects($userId);
        if (count($projects) >= Config::$maxFilesPerUser) {
            $api->sendMessage(
                $chatId,
                '⚠️ You have reached your limit of ' . Config::$maxFilesPerUser .
                ' hosted projects. Delete one from *Check Files* before adding another.'
            );
            return;
        }

        UserState::clear($userId);
        UserState::set($userId, 'state', 'await_project_name');
        $api->sendMessage($chatId, '📝 **Choose a name for this project** (letters/numbers/underscore only):');
    }

    /** Handles the plain-text steps: project name, main file name. */
    public static function routeText(TelegramApi $api, array $message): void
    {
        $userId = (int) $message['from']['id'];
        $chatId = $message['chat']['id'];
        $state = UserState::value($userId, 'state');
        if (!$state) {
            return;
        }
        $text = trim($message['text'] ?? '');

        if ($state === 'await_project_name') {
            $name = preg_replace('/[^A-Za-z0-9_]/', '', $text);
            if ($name === '') {
                $api->sendMessage($chatId, '❌ Invalid name. Use letters, numbers or underscore only.');
                return;
            }
            if (Storage::projectNameTaken($userId, $name)) {
                $api->sendMessage($chatId, '❌ You already have a project with that name. Choose another:');
                return;
            }
            UserState::set($userId, 'project_name', $name);
            UserState::set($userId, 'state', 'await_upload');
            $api->sendMessage($chatId, "📥 **Project `{$name}` reserved.**\nNow send your `.py`, `.js`, or `.zip` file.");
            return;
        }

        if ($state === 'await_main_file') {
            $projectDir = UserState::value($userId, 'project_dir');
            $filename = $text;
            $path = $projectDir ? ZipHandler::findFile($projectDir, $filename) : null;
            if (!$path) {
                $api->sendMessage($chatId, "❌ Couldn't find `{$filename}` in your uploaded project. Check the exact filename and try again:");
                return;
            }
            self::launchAndSave($api, $chatId, $userId, $path, $filename, $projectDir);
            return;
        }
    }

    public static function handleDocument(TelegramApi $api, array $message): void
    {
        $userId = (int) $message['from']['id'];
        $chatId = $message['chat']['id'];
        $state = UserState::value($userId, 'state');
        if ($state !== 'await_upload') {
            return;
        }

        $document = $message['document'];
        $fileName = $document['file_name'] ?? 'file';
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $api->sendMessage($chatId, '❌ Unsupported format. Please send `.py`, `.js`, or `.zip`.');
            return;
        }

        $sizeMb = ($document['file_size'] ?? 0) / (1024 * 1024);
        if ($sizeMb > Config::$maxUploadMb) {
            $api->sendMessage($chatId, sprintf('❌ File is %.1f MB, which exceeds the %.1f MB limit.', $sizeMb, Config::$maxUploadMb));
            return;
        }

        $projectName = UserState::value($userId, 'project_name');
        if (!$projectName) {
            $api->sendMessage($chatId, '⚠️ Session expired. Please tap *Upload File* again.');
            UserState::clear($userId);
            return;
        }

        $projectDir = self::userDir($userId) . '/' . $projectName;
        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0777, true);
        }

        $fileInfo = $api->getFile($document['file_id']);
        if (!$fileInfo || empty($fileInfo['file_path'])) {
            $api->sendMessage($chatId, '❌ Could not fetch the file from Telegram. Try again.');
            return;
        }
        $localPath = $projectDir . '/' . $fileName;
        if (!$api->downloadFile($fileInfo['file_path'], $localPath)) {
            $api->sendMessage($chatId, '❌ Download failed. Try again.');
            return;
        }

        $api->sendMessage($chatId, "✅ **Downloaded `{$fileName}`. Processing...**");

        if ($ext === 'zip') {
            $result = ZipHandler::safeExtract($localPath, $projectDir);
            @unlink($localPath);
            if (!$result['ok']) {
                $api->sendMessage($chatId, $result['message']);
                Util::rrmdir($projectDir);
                UserState::clear($userId);
                return;
            }

            $install = ZipHandler::installRequirementsIfPresent($projectDir);
            if ($install['ran']) {
                $note = $install['ok']
                    ? '✅ `requirements.txt` installed.'
                    : "⚠️ Dependency install had issues:\n```\n" . substr($install['output'], -500) . "\n```";
                $api->sendMessage($chatId, $note);
            }

            UserState::set($userId, 'project_dir', $projectDir);
            UserState::set($userId, 'state', 'await_main_file');
            $api->sendMessage($chatId, '📄 **Which file should I run?** (e.g. `bot.py` or `index.js`)');
            return;
        }

        // single .py or .js file - it IS the entry point
        self::launchAndSave($api, $chatId, $userId, $localPath, $fileName, $projectDir);
    }

    private static function launchAndSave(TelegramApi $api, int|string $chatId, int $userId, string $entryPath, string $entryName, string $projectDir): void
    {
        $projectName = UserState::value($userId, 'project_name');
        $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

        if ($ext === 'py') {
            $check = SyntaxCheck::checkPython($entryPath);
            if (!$check['ok']) {
                $api->sendMessage($chatId, "❌ **Error in script pre-check for `{$entryName}`:**\n```text\n{$check['message']}\n```");
                Util::rrmdir($projectDir);
                UserState::clear($userId);
                return;
            }
            $launch = ProcessManager::start($userId, $projectName, $entryPath, $projectDir, 'python');
        } elseif ($ext === 'js') {
            $launch = ProcessManager::start($userId, $projectName, $entryPath, $projectDir, 'node');
        } else {
            $launch = ['pid' => null, 'error' => 'Unsupported entry file type.'];
        }

        Storage::addProject($userId, $projectName, $projectDir, $entryName);

        if (!empty($launch['pid'])) {
            Storage::updateProjectPid($userId, $projectName, $launch['pid']);
            $api->sendMessage(
                $chatId,
                "✅ **`{$entryName}` started!**\n🆔 **PID:** `{$launch['pid']}`\n📦 **Project:** `{$projectName}`"
            );
        } else {
            $errText = $launch['error'] ? ": {$launch['error']}" : '.';
            $api->sendMessage($chatId, "⚠️ Project `{$projectName}` saved, but it did not start automatically{$errText}");
        }

        UserState::clear($userId);
    }
}

