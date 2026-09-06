<?php
/**
 * Handlers/Start.php
 */

final class StartHandler
{
    public static function mainMenuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📥 Upload File', 'callback_data' => 'menu_upload'],
                    ['text' => '📁 Check Files', 'callback_data' => 'menu_check_files'],
                ],
                [
                    ['text' => '⚡ Bot Speed', 'callback_data' => 'menu_speed'],
                    ['text' => '📊 Statistics', 'callback_data' => 'menu_stats'],
                ],
                [['text' => '📞 Contact Owner', 'url' => Config::$ownerLink]],
                [['text' => '📢 Updates Channel', 'url' => Config::$channelLink]],
            ],
        ];
    }

    public static function dashboardText(int $userId, string $firstName, ?string $username): string
    {
        $projects = Storage::getUserProjects($userId);
        $uname = $username ? "@{$username}" : 'N/A';
        return
            "〽️ **Welcome to " . Config::$botName . ", {$firstName}!**\n\n" .
            "🆔 **Your User ID:** `{$userId}`\n" .
            "❇️ **Username:** {$uname}\n" .
            "🔰 **Status:** `FREE` Free User\n" .
            "📂 **Projects Hosted:** " . count($projects) . ' / ' . Config::$maxFilesPerUser . "\n\n" .
            "🤖 Host & run Python (`.py`) or JS (`.js`) scripts, or a full `.zip` project.\n" .
            "👇 Use the buttons below or type /start anytime.";
    }

    public static function handle(TelegramApi $api, array $message): void
    {
        $chatId = $message['chat']['id'];
        $from = $message['from'];
        $userId = (int) $from['id'];

        UserState::clear($userId);
        Storage::registerUser($userId, $from['username'] ?? null, $from['first_name'] ?? null);

        if (!Storage::isBotEnabled() && !in_array($userId, Config::$adminIds, true)) {
            $api->sendMessage($chatId, '🚧 The bot is currently under maintenance. Please check back soon.');
            return;
        }

        $text = self::dashboardText($userId, $from['first_name'] ?? '', $from['username'] ?? null);
        $api->sendMessage($chatId, $text, self::mainMenuKeyboard());
    }
}
