<?php
/**
 * Handlers/Buttons.php
 */

final class ButtonsHandler
{
    private static float $startTime;

    public static function init(): void
    {
        self::$startTime = microtime(true);
    }

    private static function projectsKeyboard(int $userId): array
    {
        $projects = Storage::getUserProjects($userId);
        $rows = [];
        foreach ($projects as $name => $info) {
            $running = ProcessManager::isRunning($userId, $name);
            $dot = $running ? '🟢' : '🔴';
            $rows[] = [['text' => "{$dot} {$name}", 'callback_data' => "proj_{$name}"]];
        }
        $rows[] = [['text' => '⬅️ Back', 'callback_data' => 'menu_back']];
        return ['inline_keyboard' => $rows];
    }

    private static function projectDetailKeyboard(string $name): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '⏹ Stop/Restart', 'callback_data' => "stop_{$name}"],
                    ['text' => '🗑 Delete', 'callback_data' => "delete_{$name}"],
                ],
                [['text' => '⬅️ Back to Files', 'callback_data' => 'menu_check_files']],
            ],
        ];
    }

    public static function handle(TelegramApi $api, array $callbackQuery): void
    {
        $data = $callbackQuery['data'];
        $from = $callbackQuery['from'];
        $userId = (int) $from['id'];
        $message = $callbackQuery['message'];
        $chatId = $message['chat']['id'];
        $messageId = $message['message_id'];

        $api->answerCallbackQuery($callbackQuery['id']);

        if ($data === 'menu_back') {
            $text = StartHandler::dashboardText($userId, $from['first_name'] ?? '', $from['username'] ?? null);
            $api->editMessageText($chatId, $messageId, $text, StartHandler::mainMenuKeyboard());
            return;
        }

        if ($data === 'menu_check_files') {
            $projects = Storage::getUserProjects($userId);
            $text = empty($projects)
                ? "📁 **You have no hosted projects yet.**\nUse *Upload File* from /start to host one."
                : "📁 **Your hosted projects:**\n🟢 running   🔴 stopped";
            $api->editMessageText($chatId, $messageId, $text, self::projectsKeyboard($userId));
            return;
        }

        if (str_starts_with($data, 'proj_')) {
            $name = substr($data, strlen('proj_'));
            $projects = Storage::getUserProjects($userId);
            if (!isset($projects[$name])) {
                $api->editMessageText($chatId, $messageId, '⚠️ That project no longer exists.');
                return;
            }
            $info = $projects[$name];
            $running = ProcessManager::isRunning($userId, $name);
            $status = $running ? '🟢 Running' : '🔴 Stopped';
            $text =
                "📦 **Project:** `{$name}`\n" .
                "🚀 **Main file:** `{$info['main_file']}`\n" .
                "📶 **Status:** {$status}\n" .
                "🕒 **Created:** {$info['created']}";
            $api->editMessageText($chatId, $messageId, $text, self::projectDetailKeyboard($name));
            return;
        }

        if (str_starts_with($data, 'stop_')) {
            $name = substr($data, strlen('stop_'));
            $stopped = ProcessManager::stop($userId, $name);
            Storage::updateProjectPid($userId, $name, null);
            $text = $stopped ? "⏹ **`{$name}` stopped.**" : "ℹ️ `{$name}` was not running.";
            $api->editMessageText($chatId, $messageId, $text, self::projectsKeyboard($userId));
            return;
        }

        if (str_starts_with($data, 'delete_')) {
            $name = substr($data, strlen('delete_'));
            $projects = Storage::getUserProjects($userId);
            ProcessManager::stop($userId, $name);
            if (isset($projects[$name]['folder']) && is_dir($projects[$name]['folder'])) {
                Util::rrmdir($projects[$name]['folder']);
            }
            Storage::deleteProject($userId, $name);
            $api->editMessageText($chatId, $messageId, "🗑 **Project `{$name}` deleted.**", self::projectsKeyboard($userId));
            return;
        }

        if ($data === 'menu_speed') {
            $t0 = microtime(true);
            $pingMs = round((microtime(true) - $t0) * 1000, 1);
            $uptime = (int) (microtime(true) - self::$startTime);
            $h = intdiv($uptime, 3600);
            $m = intdiv($uptime % 3600, 60);
            $s = $uptime % 60;
            $api->sendMessage(
                $chatId,
                "⚡ **Bot Speed & Status:**\n\n" .
                "⏱ **Response Time:** {$pingMs} ms\n" .
                "🟢 **Bot Status:** Online\n" .
                "⏳ **Uptime:** {$h}h {$m}m {$s}s"
            );
            return;
        }

        if ($data === 'menu_stats') {
            $summary = Storage::statsSummary();
            $api->sendMessage(
                $chatId,
                "📊 **Server Statistics:**\n\n" .
                "👤 **Total Users:** {$summary['total_users']}\n" .
                "📦 **Total Projects:** {$summary['total_projects']}\n" .
                "🟢 **Currently Running:** {$summary['running']}"
            );
            return;
        }
    }

}
