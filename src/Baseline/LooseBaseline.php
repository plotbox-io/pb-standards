<?php

declare(strict_types=1);

namespace PlotBox\Standards\Baseline;

use InvalidArgumentException;

/**
 * A count-based baseline in Mago's "loose" TOML format:
 *
 *     variant = "loose"
 *
 *     [[issues]]
 *     file = "classes/Foo.php"
 *     code = "some-code"
 *     message = "Some message"
 *     count = 2
 *
 * An issue is allowed while its file still reports no more than `count` issues with the same
 * code and message. Mago reads this format natively; other checks use {@see self::unmatched()}.
 *
 * Only the subset of TOML that Mago writes is parsed: one `key = value` per line, basic,
 * literal and single-line multi-line strings, and integers.
 */
final class LooseBaseline
{
    private const VARIANT = 'loose';

    /** @param array<string, int> $counts keyed by {@see BaselineIssue::key()} */
    private function __construct(private readonly array $counts)
    {
    }

    /** @param iterable<BaselineIssue> $issues */
    public static function fromIssues(iterable $issues): self
    {
        $counts = [];
        foreach ($issues as $issue) {
            $key = $issue->key();
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return new self($counts);
    }

    public static function fromFile(string $path): self
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read baseline file: $path");
        }

        return self::fromToml($contents);
    }

    public static function fromToml(string $toml): self
    {
        $counts = [];
        $entry = null;
        $variant = null;
        foreach (preg_split('/\R/', $toml) ?: [] as $index => $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if ($line === '[[issues]]') {
                self::addEntry($counts, $entry, $index);
                $entry = [];
                continue;
            }
            [$name, $value] = self::parseAssignment($line, $index + 1);
            if ($entry === null) {
                if ($name !== 'variant') {
                    throw new InvalidArgumentException(
                        sprintf('Line %d: unexpected key "%s" before [[issues]]', $index + 1, $name)
                    );
                }
                $variant = $value;
                continue;
            }
            $entry[$name] = $value;
        }
        self::addEntry($counts, $entry, null);

        if ($variant !== self::VARIANT) {
            throw new InvalidArgumentException('Only variant = "loose" baselines are supported');
        }

        return new self($counts);
    }

    /** @return array<string, int> keyed by {@see BaselineIssue::key()} */
    public function counts(): array
    {
        return $this->counts;
    }

    public function issueCount(): int
    {
        return array_sum($this->counts);
    }

    /**
     * Issues the baseline does not cover. When a file reports more issues of one code and
     * message than the baseline allows, every occurrence is returned: a loose baseline cannot
     * tell which of them is new.
     *
     * @template T of BaselineIssue
     * @param list<T> $issues
     * @return list<T>
     */
    public function unmatched(array $issues): array
    {
        $found = [];
        foreach ($issues as $issue) {
            $key = $issue->key();
            $found[$key] = ($found[$key] ?? 0) + 1;
        }

        $unmatched = [];
        foreach ($issues as $issue) {
            $key = $issue->key();
            if ($found[$key] > ($this->counts[$key] ?? 0)) {
                $unmatched[] = $issue;
            }
        }

        return $unmatched;
    }

    /**
     * Entries whose issues no longer occur at all (or occur fewer times) in $issues.
     *
     * @param list<BaselineIssue> $issues
     */
    public function outdatedEntryCount(array $issues): int
    {
        $found = [];
        foreach ($issues as $issue) {
            $key = $issue->key();
            $found[$key] = ($found[$key] ?? 0) + 1;
        }

        $outdated = 0;
        foreach ($this->counts as $key => $count) {
            if (($found[$key] ?? 0) < $count) {
                $outdated++;
            }
        }

        return $outdated;
    }

    public function toToml(): string
    {
        $keys = array_keys($this->counts);
        usort($keys, static fn (string $a, string $b): int => strcmp($a, $b));

        $toml = 'variant = "' . self::VARIANT . "\"\n";
        foreach ($keys as $key) {
            [$file, $code, $message] = BaselineIssue::splitKey($key);
            $toml .= "\n[[issues]]\n"
                . 'file = ' . self::quote($file) . "\n"
                . 'code = ' . self::quote($code) . "\n"
                . 'message = ' . self::quote($message) . "\n"
                . 'count = ' . $this->counts[$key] . "\n";
        }

        return $toml;
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, string|int>|null $entry
     */
    private static function addEntry(
        array &$counts,
        ?array $entry,
        ?int $lineIndex
    ): void {
        if ($entry === null) {
            return;
        }
        $file = $entry['file'] ?? null;
        $code = $entry['code'] ?? null;
        $message = $entry['message'] ?? null;
        $count = $entry['count'] ?? 1;
        if (!is_string($file) || !is_string($code) || !is_string($message) || !is_int($count) || $count < 1) {
            throw new InvalidArgumentException(sprintf(
                'Incomplete [[issues]] entry ending before line %s: needs file, code, message and a positive count',
                $lineIndex === null ? 'EOF' : (string) ($lineIndex + 1)
            ));
        }
        $key = BaselineIssue::makeKey($file, $code, $message);
        $counts[$key] = ($counts[$key] ?? 0) + $count;
    }

    /** @return array{0: string, 1: string|int} */
    private static function parseAssignment(string $line, int $lineNumber): array
    {
        if (preg_match('/^([A-Za-z0-9_-]+)\s*=\s*(.+)$/', $line, $matches) !== 1) {
            throw new InvalidArgumentException("Line $lineNumber: expected key = value");
        }

        return [$matches[1], self::parseValue($matches[2], $lineNumber)];
    }

    private static function parseValue(string $raw, int $lineNumber): string|int
    {
        if (preg_match('/^-?\d+$/', $raw) === 1) {
            return (int) $raw;
        }
        foreach (["'''", '"""'] as $delimiter) {
            if (strlen($raw) >= 6 && str_starts_with($raw, $delimiter) && str_ends_with($raw, $delimiter)) {
                $inner = substr($raw, 3, -3);
                return $delimiter === "'''"
                    ? $inner
                    : self::unescapeBasic($inner, $lineNumber);
            }
        }
        if (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'")) {
            return substr($raw, 1, -1);
        }
        if (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"')) {
            return self::unescapeBasic(substr($raw, 1, -1), $lineNumber);
        }

        throw new InvalidArgumentException("Line $lineNumber: unsupported value $raw");
    }

    private static function unescapeBasic(string $inner, int $lineNumber): string
    {
        $result = preg_replace_callback(
            '/\\\\(?:u([0-9A-Fa-f]{4})|U([0-9A-Fa-f]{8})|(.))/s',
            static function (array $match) use ($lineNumber): string {
                if (($match[1] ?? '') !== '' || ($match[2] ?? '') !== '') {
                    $codePoint = hexdec($match[1] !== '' ? $match[1] : $match[2]);
                    return mb_chr((int) $codePoint, 'UTF-8');
                }
                return match ($match[3]) {
                    '\\' => '\\',
                    '"' => '"',
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    'b' => "\x08",
                    'f' => "\f",
                    default => throw new InvalidArgumentException(
                        "Line $lineNumber: unsupported escape \\{$match[3]}"
                    ),
                };
            },
            $inner
        );

        return (string) $result;
    }

    private static function quote(string $value): string
    {
        $escaped = strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            "\x08" => '\\b',
            "\f" => '\\f',
        ]);
        $escaped = (string) preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static fn (array $match): string => sprintf('\\u%04X', ord($match[0])),
            $escaped
        );

        return '"' . $escaped . '"';
    }
}
