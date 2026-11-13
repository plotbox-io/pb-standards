<?php

declare(strict_types=1);

namespace PlotBox\Standards\Baseline;

/**
 * Checks that a loose baseline only shrank between two revisions. Allowed changes: lower
 * counts, removed entries, and entries that moved with a file git reports as renamed.
 * Anything else (a new entry or a higher count) hides a new issue and is a violation.
 */
final class LooseBaselineRatchet
{
    /**
     * @param array<string, string> $renames old path => new path
     * @return list<string> human-readable violations, empty when the change is allowed
     */
    public function violations(
        LooseBaseline $before,
        LooseBaseline $after,
        array $renames = []
    ): array {
        $allowed = [];
        foreach ($before->counts() as $key => $count) {
            [$file, $code, $message] = BaselineIssue::splitKey($key);
            $movedKey = BaselineIssue::makeKey($renames[$file] ?? $file, $code, $message);
            $allowed[$movedKey] = ($allowed[$movedKey] ?? 0) + $count;
        }

        $violations = [];
        foreach ($after->counts() as $key => $count) {
            $limit = $allowed[$key] ?? 0;
            if ($count <= $limit) {
                continue;
            }
            [$file, $code, $message] = BaselineIssue::splitKey($key);
            $violations[] = $limit === 0
                ? sprintf('New entry: %s [%s] %s', $file, $code, $message)
                : sprintf('Count raised from %d to %d: %s [%s] %s', $limit, $count, $file, $code, $message);
        }

        return $violations;
    }
}
