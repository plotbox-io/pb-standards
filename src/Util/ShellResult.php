<?php

declare(strict_types=1);

namespace PlotBox\Standards\Util;

final class ShellResult
{
    public readonly ?string $stdOut;
    public readonly ?string $stdErr;
    public readonly int $exitCode;

    public function __construct(
        ?string $stdOut,
        ?string $stdErr,
        int $exitCode
    ) {
        $this->stdOut = $stdOut;
        $this->stdErr = $stdErr;
        $this->exitCode = $exitCode;
    }

    public function allOutput(): string
    {
        return (string) $this->stdOut . (string) $this->stdErr;
    }
}
