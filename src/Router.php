<?php
/**
 * Router.php
 * -----------
 * Dispatches a single Telegram update to the right handler.
 */

final class Router
{
    public static function handle(TelegramApi $api, array $update): void
    {
        try {
            if (isset($update['callback_query'])) {
                self::handleCallback($api, $update['callback_query']);
                return;
            }
            if (isset($update['message'])) {
                self::handleMessage($api, $update['message']);
                return;
            }
        } catch (Throwable $e) {
            error_log('Router error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    private static function handleCallback(TelegramApi $api, array $cq): void
    {
        $data = $cq['data'] ?? '';

        if ($data === 'menu_upload') {
            UploadHandler::entry($api, $cq);
            return;
        }
        if (str_starts_with($data, 'admin_')) {
            AdminHandler::handleCallback($api, $cq);
            return;
        }
        if (in_array($data, ['menu_check_files', 'menu_back', 'menu_speed', 'menu_stats'], true)
            || str_starts_with($data, 'proj_')
            || str_starts_with($data, 'stop_')
            || str_starts_with($data, 'delete_')
        ) {
            ButtonsHandler::handle($api, $cq);
            return;
        }
    }

    private static function handleMessage(TelegramApi $api, array $message): void
    {
        $text = $message['text'] ?? null;
        $userId = (int) ($message['from']['id'] ?? 0);

        if ($text !== null && str_starts_with($text, '/start')) {
            StartHandler::handle($api, $message);
            return;
        }
        if ($text !== null && str_starts_with($text, '/admin')) {
            AdminHandler::panel($api, $message);
            return;
        }
        if ($text !== null && str_starts_with($text, '/cancel')) {
            AdminHandler::cancel($api, $message);
            return;
        }

        $state = UserState::value($userId, 'state');

        if ($state === 'await_broadcast') {
            AdminHandler::broadcast($api, $message);
            return;
        }

        if (isset($message['document'])) {
            UploadHandler::handleDocument($api, $message);
            return;
        }

        if ($text !== null) {
            UploadHandler::routeText($api, $message);
        }
    }
}
