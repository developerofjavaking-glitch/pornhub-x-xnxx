<?php
/**
 * Storage.php
 * -----------
 * Tiny JSON-file "database" - no external DB service required. Uses flock()
 * for safe read-modify-write even though the bot is normally single-process.
 */

final class Storage
{
    private static function defaultData(): array
    {
        return [
            'bot_enabled' => true,
            'users' => new stdClass(),
            'projects' => new stdClass(),
        ];
    }

    /** Read-modify-write a section under an exclusive file lock. */
    private static function transact(callable $fn)
    {
        $fp = fopen(Config::$dbFile, 'c+');
        flock($fp, LOCK_EX);

        $raw = stream_get_contents($fp);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = json_decode(json_encode(self::defaultData()), true);
        }

        $result = $fn($data);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $result;
    }

    public static function registerUser(int $userId, ?string $username, ?string $firstName): void
    {
        self::transact(function (array &$d) use ($userId, $username, $firstName) {
            $uid = (string) $userId;
            if (!isset($d['users'][$uid])) {
                $d['users'][$uid] = [
                    'username' => $username ?? '',
                    'first_name' => $firstName ?? '',
                    'joined' => gmdate('c'),
                ];
            }
        });
    }

    public static function isBotEnabled(): bool
    {
        return self::transact(fn(array &$d) => $d['bot_enabled'] ?? true);
    }

    public static function setBotEnabled(bool $value): void
    {
        self::transact(function (array &$d) use ($value) {
            $d['bot_enabled'] = $value;
        });
    }

    public static function getUserProjects(int $userId): array
    {
        return self::transact(fn(array &$d) => $d['projects'][(string) $userId] ?? []);
    }

    public static function projectNameTaken(int $userId, string $name): bool
    {
        $projects = self::getUserProjects($userId);
        return isset($projects[$name]);
    }

    public static function addProject(int $userId, string $name, string $folder, string $mainFile): void
    {
        self::transact(function (array &$d) use ($userId, $name, $folder, $mainFile) {
            $uid = (string) $userId;
            if (!isset($d['projects'][$uid])) {
                $d['projects'][$uid] = [];
            }
            $d['projects'][$uid][$name] = [
                'main_file' => $mainFile,
                'folder' => $folder,
                'pid' => null,
                'created' => gmdate('c'),
            ];
        });
    }

    public static function updateProjectPid(int $userId, string $name, ?int $pid): void
    {
        self::transact(function (array &$d) use ($userId, $name, $pid) {
            $uid = (string) $userId;
            if (isset($d['projects'][$uid][$name])) {
                $d['projects'][$uid][$name]['pid'] = $pid;
            }
        });
    }

    public static function deleteProject(int $userId, string $name): void
    {
        self::transact(function (array &$d) use ($userId, $name) {
            $uid = (string) $userId;
            unset($d['projects'][$uid][$name]);
        });
    }

    public static function allUserIds(): array
    {
        return self::transact(fn(array &$d) => array_map('intval', array_keys($d['users'])));
    }

    public static function statsSummary(): array
    {
        return self::transact(function (array &$d) {
            $totalUsers = count($d['users']);
            $totalProjects = 0;
            $running = 0;
            foreach ($d['projects'] as $userProjects) {
                $totalProjects += count($userProjects);
                foreach ($userProjects as $proj) {
                    if (!empty($proj['pid'])) {
                        $running++;
                    }
                }
            }
            return ['total_users' => $totalUsers, 'total_projects' => $totalProjects, 'running' => $running];
        });
    }
}
