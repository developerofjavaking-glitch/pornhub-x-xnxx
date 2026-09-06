<?php
/**
 * Handlers/Admin.php
 */

final class AdminHandler
{
    private static function isAdmin(int $userId): bool
    {
        return in_array($userId, Config::$adminIds, true);
    }

    private static function keyboard(): array
    {
        return [
            'inline_keyboard' => [
                [['text' => '📊 Stats', 'callback_data' => 'admin_stats']],
                [['text' => '🔌 Toggle Bot On/Off', 'callback_data' => 'admin_toggle']],
                [['text' => '📣 Broadcast', 'callback_data' => 'admin_broadcast']],
            ],
        ];
    }

    public static function panel(TelegramApi $api, array $message): void
    {
        $userId = (int) $message['from']['id'];
        $chatId = $message['chat']['id'];
        if (!self::isAdmin($userId)) {
            $api->sendMessage($chatId, '⛔ You are not authorized to use this command.');
            return;
        }
        $api->sendMessage($chatId, '🛠 **Admin Panel**', self::keyboard());
    }

    public static function handleCallback(TelegramApi $api, array $callbackQuery): void
    {
        $userId = (int) $callbackQuery['from']['id'];
        $chatId = $callbackQuery['message']['chat']['id'];

        if (!self::isAdmin($userId)) {
            $api->answerCallbackQuery($callbackQuery['id'], '⛔ Not authorized.', true);
            return;
        }
        $api->answerCallbackQuery($callbackQuery['id']);
        $data = $callbackQuery['data'];

        if ($data === 'admin_stats') {
            $summary = Storage::statsSummary();
            $api->sendMessage(
                $chatId,
                "📊 **Admin Statistics**\n\n" .
                "👤 Users: {$summary['total_users']}\n" .
                "📦 Projects: {$summary['total_projects']}\n" .
                "🟢 Running: {$summary['running']}"
            );
            return;
        }

        if ($data === 'admin_toggle') {
            $current = Storage::isBotEnabled();
            Storage::setBotEnabled(!$current);
            $stateText = !$current ? '🔴 DISABLED' : '🟢 ENABLED';
            $api->sendMessage($chatId, "🔌 Bot is now {$stateText} for regular users.");
            return;
        }

        if ($data === 'admin_broadcast') {
            UserState::set($userId, 'state', 'await_broadcast');
            $api->sendMessage($chatId, '📣 Send the message (text, photo, or document) you want to broadcast to all users, or /cancel to abort.');
            return;
        }
    }

    /** Handles the actual content once an admin is in the 'await_broadcast' state. */
    public static function broadcast(TelegramApi $api, array $message): void
    {
        $userId = (int) $message['from']['id'];
        $chatId = $message['chat']['id'];
        if (UserState::value($userId, 'state') !== 'await_broadcast' || !self::isAdmin($userId)) {
            return;
        }

        UserState::clear($userId);
        $targets = Storage::allUserIds();
        $sent = 0;
        $failed = 0;

        foreach ($targets as $uid) {
            $result = $api->copyMessage($uid, $chatId, $message['message_id']);
            $result !== null ? $sent++ : $failed++;
        }

        $api->sendMessage($chatId, "📣 Broadcast complete. ✅ Sent: {$sent}  ❌ Failed: {$failed}");
    }

    public static function cancel(TelegramApi $api, array $message): void
    {
        $userId = (int) $message['from']['id'];
        UserState::clear($userId);
        $api->sendMessage($message['chat']['id'], '❎ Cancelled.');
    }
}
