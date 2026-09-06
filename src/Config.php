<?php
/**
 * Config.php
 * ----------
 * Every deployment-specific value comes from an environment variable, so
 * the same image runs unmodified on Render (Docker) or anywhere else.
 */

final class Config
{
    public static string $botToken;
    public static array $adminIds;
    public static string $ownerLink;
    public static string $channelLink;
    public static string $botName;

    public static int $maxFilesPerUser;
    public static float $maxUploadMb;

    public static string $dataDir;
    public static string $uploadDir;
    public static string $dbFile;

    public static bool $enableKeepalive;
    public static int $port;

    public static function load(): void
    {
        self::$botToken = getenv('BOT_TOKEN') ?: '';

        $adminRaw = getenv('ADMIN_ID') ?: getenv('ADMIN_IDS') ?: '';
        self::$adminIds = array_values(array_filter(array_map(
            fn($v) => (int) trim($v),
            explode(',', $adminRaw)
        ), fn($v) => $v !== 0));

        self::$ownerLink = getenv('OWNER_LINK') ?: 'https://t.me/';
        self::$channelLink = getenv('CHANNEL_LINK') ?: 'https://t.me/';
        self::$botName = getenv('BOT_NAME') ?: 'Boom Host Bot';

        self::$maxFilesPerUser = (int) (getenv('MAX_FILES_PER_USER') ?: 3);
        self::$maxUploadMb = (float) (getenv('MAX_UPLOAD_MB') ?: 1);

        // DATA_DIR: no env var needed - defaults to /app/data inside the
        // Docker image (this file lives at /app/src/Config.php).
        self::$dataDir = getenv('DATA_DIR') ?: (__DIR__ . '/../data');
        self::$uploadDir = getenv('UPLOAD_DIR') ?: (self::$dataDir . '/upload_bots');
        self::$dbFile = self::$dataDir . '/database.json';

        // PORT: Render injects this automatically for "Web Service" deploys;
        // falls back to 8080 for local/Background-Worker runs. No manual
        // env var needed either way.
        $portEnv = getenv('PORT');
        self::$port = (int) ($portEnv ?: 8080);

        // ENABLE_KEEPALIVE: auto-detected from whether the platform gave us
        // a PORT. Render only sets PORT for "Web Service" deploys, which are
        // the ones that need a bound port to pass health checks - so this
        // just does the right thing without any env var being set.
        // Can still be overridden explicitly if ever needed.
        $keepAliveEnv = getenv('ENABLE_KEEPALIVE');
        self::$enableKeepalive = $keepAliveEnv !== false
            ? strtolower($keepAliveEnv) === 'true'
            : ($portEnv !== false);

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }
        if (!is_dir(self::$uploadDir)) {
            mkdir(self::$uploadDir, 0777, true);
        }
    }
}
