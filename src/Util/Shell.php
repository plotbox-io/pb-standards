<?php

declare(strict_types=1);

namespace PlotBox\Standards\Util;

final class Shell
{
    /** @see https://stackoverflow.com/a/25879953 */
    public static function exec(string $cmd): ShellResult
    {
        $proc = proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $resultCode = proc_close($proc);

        return new ShellResult($stdout, $stderr, $resultCode);
    }
}
