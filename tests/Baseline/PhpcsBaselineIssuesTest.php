<?php

declare(strict_types=1);

namespace PlotBox\Standards\Tests\Baseline;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PlotBox\Standards\Baseline\BaselineIssue;
use PlotBox\Standards\Baseline\PhpcsBaselineIssues;
use PlotBox\Standards\Command\SarbCodeIssue;

final class PhpcsBaselineIssuesTest extends TestCase
{
    #[Test]
    public function should_key_report_messages_by_relative_file_and_sniff_code(): void
    {
        $json = json_encode([
        'files' => [
            '/app/classes/A.php' => [
        'messages' => [
                ['source' => 'Generic.Files.LineLength.TooLong', 'message' => 'Line too long', 'line' => 3],
            ]
            ],
            'tests/B.php' => ['messages' => []],
        ]
        ]);

        self::assertEquals(
            [new BaselineIssue('classes/A.php', 'Generic.Files.LineLength.TooLong', 'Line too long')],
            PhpcsBaselineIssues::fromJsonReport((string) $json, ['/app/'])
        );
    }

    #[Test]
    public function should_use_the_sniff_source_as_the_issue_code(): void
    {
        $issue = SarbCodeIssue::fromPhpcs('a.php', (object) [
            'line' => 1,
            'type' => 'ERROR',
            'message' => 'm',
            'severity' => 5,
            'source' => 'PlotBox.Some.Sniff',
        ]);

        self::assertSame('PlotBox.Some.Sniff', PhpcsBaselineIssues::code($issue));
    }
}
