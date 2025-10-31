<?php

declare(strict_types=1);

namespace PlotBox\Standards\Util;

final readonly class ShellResult
{
    public function __construct(
        public ?string $stdOut,
        public ?string $stdErr,
        public int $exitCode
    ) {
    }

    public function allOutput(): string
    {
        return (string) $this->stdOut . (string) $this->stdErr;
    }
}
