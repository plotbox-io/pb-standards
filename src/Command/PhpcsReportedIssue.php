<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use stdClass;

final class PhpcsReportedIssue
{
    public function __construct(
        public string $file,
        public int $line,
        public string $type,
        public string $message,
        public string $severity,
        public stdClass $originalToolDetails
    ) {
    }

    public static function fromPhpcs(string $file, object $phpcs): self
    {
        return new self(
            $file,
            $phpcs->line,
            $phpcs->type,
            $phpcs->message,
            (string) $phpcs->severity,
            $phpcs
        );
    }
}
