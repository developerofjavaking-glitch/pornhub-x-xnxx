<?php
/**
 * SyntaxCheck.php
 * ----------------
 * Uses the container's own `python3 -m py_compile` to pre-flight-validate
 * uploaded .py files, mirroring Python's compile() error format.
 */

final class SyntaxCheck
{
    /** Returns ['ok' => bool, 'message' => string]. */
    public static function checkPython(string $filePath): array
    {
        $cmd = 'python3 -m py_compile ' . escapeshellarg($filePath) . ' 2>&1';
        exec($cmd, $outputLines, $exitCode);

        if ($exitCode === 0) {
            return ['ok' => true, 'message' => ''];
        }

        $message = implode("\n", $outputLines);
        // Trim compileall's own wrapper noise, keep the SyntaxError block.
        return ['ok' => false, 'message' => trim($message)];
    }
}
