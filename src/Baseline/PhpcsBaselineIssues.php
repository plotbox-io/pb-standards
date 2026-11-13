<?php

declare(strict_types=1);

namespace PlotBox\Standards\Baseline;

use PlotBox\Standards\Command\PhpcsReportedIssue;
use InvalidArgumentException;

/**
 * Maps phpcs results onto loose-baseline issues. The sniff code (`source`) is the issue code.
 */
final class PhpcsBaselineIssues
{
    public static function code(PhpcsReportedIssue $issue): string
    {
        $source = $issue->originalToolDetails->source ?? null;

        return is_string($source) && $source !== '' ? $source : $issue->type;
    }

    /**
     * @param list<string> $rootPrefixes stripped from phpcs' absolute file paths
     * @return list<BaselineIssue>
     */
    public static function fromJsonReport(string $json, array $rootPrefixes): array
    {
        $report = json_decode($json);
        if (!is_object($report) || !isset($report->files) || !is_object($report->files)) {
            throw new InvalidArgumentException('phpcs did not return a JSON report');
        }

        $issues = [];
        foreach (get_object_vars($report->files) as $file => $details) {
            $relative = self::relative((string) $file, $rootPrefixes);
            foreach ($details->messages ?? [] as $message) {
                $issues[] = new BaselineIssue($relative, (string) $message->source, (string) $message->message);
            }
        }

        return $issues;
    }

    /** @param list<string> $rootPrefixes */
    private static function relative(string $file, array $rootPrefixes): string
    {
        foreach ($rootPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($file, $prefix)) {
                return substr($file, strlen($prefix));
            }
        }

        return $file;
    }
}
