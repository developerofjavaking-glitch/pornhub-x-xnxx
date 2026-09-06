<?php
/**
 * bot.php — entry point.
 *
 * Run with: php src/bot.php
 * All configuration comes from environment variables (see Config.php / README.md).
 */

declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/TelegramApi.php';
require_once __DIR__ . '/SyntaxCheck.php';
require_once __DIR__ . '/ZipHandler.php';
require_once __DIR__ . '/ProcessManager.php';
require_once __DIR__ . '/UserState.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/Handlers/Start.php';
require_once __DIR__ . '/Handlers/Buttons.php';
require_once __DIR__ . '/Handlers/Upload.php';
require_once __DIR__ . '/Handlers/Admin.php';

Config::load();
ButtonsHandler::init();

function logLine(string $msg): void
{
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] {$msg}\n");
}

if (Config::$botToken === '') {
    logLine('ERROR: BOT_TOKEN is not set. Add it as an environment variable and restart.');
    exit(1);
}
if (empty(Config::$adminIds)) {
    logLine('WARNING: ADMIN_ID is not set — /admin will be unusable until you set it.');
}

$api = new TelegramApi(Config::$botToken);

logLine(Config::$botName . ' starting (long polling)...');

$offset = 0;
while (true) {
    try {
        $updates = $api->getUpdates($offset, 30);
        foreach ($updates as $update) {
            $offset = $update['update_id'] + 1;
            Router::handle($api, $update);
        }
    } catch (Throwable $e) {
        logLine('Polling error: ' . $e->getMessage());
        sleep(2);
    }
}
