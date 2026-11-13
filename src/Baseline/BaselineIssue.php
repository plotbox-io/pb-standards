<?php

declare(strict_types=1);

namespace PlotBox\Standards\Baseline;

/**
 * One reported issue, keyed the way a loose baseline matches it: file, code and message.
 * Line numbers are deliberately absent so edits elsewhere in a file never invalidate the baseline.
 */
final class BaselineIssue
{
    public function __construct(
        public readonly string $file,
        public readonly string $code,
        public readonly string $message
    ) {
    }

    public function key(): string
    {
        return self::makeKey($this->file, $this->code, $this->message);
    }

    public static function makeKey(
        string $file,
        string $code,
        string $message
    ): string {
        return $file . "\0" . $code . "\0" . $message;
    }

    /** @return array{0: string, 1: string, 2: string} file, code, message */
    public static function splitKey(string $key): array
    {
        $parts = explode("\0", $key, 3);

        return [$parts[0], $parts[1] ?? '', $parts[2] ?? ''];
    }
}
