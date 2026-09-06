<?php
/**
 * ZipHandler.php
 * ---------------
 * Safe zip extraction (zip-slip protected) plus optional
 * `pip install -r requirements.txt` for the extracted project.
 */

final class ZipHandler
{
    /** Returns ['ok' => bool, 'message' => string]. */
    public static function safeExtract(string $zipPath, string $destDir): array
    {
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'message' => '❌ That file is not a valid ZIP archive.'];
        }

        $absDest = realpath($destDir);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            $target = $absDest . DIRECTORY_SEPARATOR . $name;
            $normalized = str_replace('\\', '/', $target);
            if (strpos($normalized, '..') !== false || strpos($normalized, $absDest) !== 0) {
                $zip->close();
                return ['ok' => false, 'message' => "❌ Blocked unsafe path in zip: {$name}"];
            }
        }

        $zip->extractTo($destDir);
        $zip->close();
        return ['ok' => true, 'message' => 'ok'];
    }

    /** Returns ['ran' => bool, 'ok' => bool, 'output' => string]. */
    public static function installRequirementsIfPresent(string $projectDir, int $timeoutSec = 180): array
    {
        $reqPath = self::findFile($projectDir, 'requirements.txt');
        if ($reqPath === null) {
            return ['ran' => false, 'ok' => true, 'output' => ''];
        }

        $cmd = sprintf(
            'timeout %d pip install --break-system-packages -r %s 2>&1',
            $timeoutSec,
            escapeshellarg($reqPath)
        );
        exec($cmd, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);
        return ['ran' => true, 'ok' => $exitCode === 0, 'output' => substr($output, -2000)];
    }

    /** Recursively search $dir for a file literally named $filename. */
    public static function findFile(string $dir, string $filename): ?string
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
        return null;
    }
}
