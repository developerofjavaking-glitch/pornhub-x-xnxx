<?php
/**
 * TelegramApi.php
 * ----------------
 * Thin cURL wrapper around the Telegram Bot API - no external SDK, so
 * `composer install` isn't a build-failure point in the Docker image.
 */

final class TelegramApi
{
    private string $base;

    public function __construct(string $token)
    {
        $this->base = "https://api.telegram.org/bot{$token}/";
    }

    /** Low level: call any Bot API method with an associative array of params. */
    public function call(string $method, array $params = [])
    {
        $ch = curl_init($this->base . $method);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Telegram API cURL error on {$method}: {$err}");
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            error_log("Telegram API error on {$method}: " . $response);
            return null;
        }
        return $decoded['result'];
    }

    public function getUpdates(int $offset, int $timeout = 30)
    {
        return $this->call('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]) ?? [];
    }

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'Markdown')
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->call('sendMessage', $params);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null, string $parseMode = 'Markdown')
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        return $this->call('editMessageText', $params);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false)
    {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }
        return $this->call('answerCallbackQuery', $params);
    }

    public function getFile(string $fileId)
    {
        return $this->call('getFile', ['file_id' => $fileId]);
    }

    /** Downloads a Telegram file (by file_path from getFile) to $destPath. */
    public function downloadFile(string $filePath, string $destPath): bool
    {
        $url = str_replace('/bot', '/file/bot', $this->base) . $filePath;
        $data = @file_get_contents($url);
        if ($data === false) {
            return false;
        }
        return file_put_contents($destPath, $data) !== false;
    }

    public function copyMessage(int|string $toChatId, int|string $fromChatId, int $messageId)
    {
        return $this->call('copyMessage', [
            'chat_id' => $toChatId,
            'from_chat_id' => $fromChatId,
            'message_id' => $messageId,
        ]);
    }
}
