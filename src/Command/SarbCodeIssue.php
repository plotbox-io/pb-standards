<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

use stdClass;

final class SarbCodeIssue
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

    public static function fromObject(object $std): self
    {
        return new self(
            $std->file,
            $std->line,
            $std->type,
            $std->message,
            $std->severity,
            $std->original_tool_details
        );
    }
}
