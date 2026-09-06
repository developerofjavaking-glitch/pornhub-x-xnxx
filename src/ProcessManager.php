<?php
/**
 * ProcessManager.php
 * -------------------
 * Starts, tracks, and terminates the background subprocesses that run each
 * user's hosted script, using proc_open(). In-memory registry only survives
 * for the life of this PHP process - a container restart naturally kills
 * the OS processes too, same as the Python version.
 */

final class ProcessManager
{
    /** @var array<string, array{proc: resource, pid: int}> */
    private static array $running = [];

    private static function key(int $userId, string $projectName): string
    {
        return $userId . ':' . $projectName;
    }

    /** Returns ['pid' => ?int, 'error' => ?string]. */
    public static function start(int $userId, string $projectName, string $entryPath, string $cwd, string $runtime): array
    {
        self::stop($userId, $projectName);

        $binary = $runtime === 'node' ? 'node' : 'python3';
        $logPath = $cwd . '/output.log';

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['file', $logPath, 'a'],
            2 => ['file', $logPath, 'a'],
        ];

        $cmd = escapeshellcmd($binary) . ' ' . escapeshellarg($entryPath);
        $proc = proc_open($cmd, $descriptorSpec, $pipes, $cwd);

        if (!is_resource($proc)) {
            return ['pid' => null, 'error' => 'Failed to launch process.'];
        }
        fclose($pipes[0]);

        $status = proc_get_status($proc);
        $pid = $status['pid'] ?? null;

        self::$running[self::key($userId, $projectName)] = ['proc' => $proc, 'pid' => $pid];
        return ['pid' => $pid, 'error' => null];
    }

    public static function stop(int $userId, string $projectName): bool
    {
        $k = self::key($userId, $projectName);
        if (!isset(self::$running[$k])) {
            return false;
        }
        $entry = self::$running[$k];
        // proc_terminate sends SIGTERM to the direct child; also try killing
        // by PID in case the child forked (covers most simple bot scripts).
        @proc_terminate($entry['proc']);
        if (!empty($entry['pid'])) {
            @exec('kill ' . escapeshellarg((string) $entry['pid']) . ' 2>/dev/null');
        }
        proc_close($entry['proc']);
        unset(self::$running[$k]);
        return true;
    }

    public static function isRunning(int $userId, string $projectName): bool
    {
        $k = self::key($userId, $projectName);
        if (!isset(self::$running[$k])) {
            return false;
        }
        $status = proc_get_status(self::$running[$k]['proc']);
        if (!$status['running']) {
            unset(self::$running[$k]);
            return false;
        }
        return true;
    }
}
