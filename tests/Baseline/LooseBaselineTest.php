<?php

declare(strict_types=1);

namespace PlotBox\Standards\Tests\Baseline;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PlotBox\Standards\Baseline\BaselineIssue;
use PlotBox\Standards\Baseline\LooseBaseline;

final class LooseBaselineTest extends TestCase
{
    #[Test]
    public function should_parse_the_string_styles_mago_writes(): void
    {
        $toml = <<<'TOML'
            variant = "loose"

            [[issues]]
            file = "classes/A.php"
            code = "class-must-be-final"
            message = 'Class `Everafter\PlotBox\A` should be declared `final`.'
            count = 2

            [[issues]]
            file = "classes/B.php"
            code = "mixed-return"
            message = '''Returned type is less specific due to nested 'mixed'.'''
            count = 1

            [[issues]]
            file = "classes/C.php"
            code = "possibly-null"
            message = "Value \"x\" may be null \\ here \u00e9"
            count = 3
            TOML;

        $baseline = LooseBaseline::fromToml($toml);

        self::assertSame([
            BaselineIssue::makeKey(
                'classes/A.php',
                'class-must-be-final',
                'Class `Everafter\PlotBox\A` should be declared `final`.'
            ) => 2,
            BaselineIssue::makeKey(
                'classes/B.php',
                'mixed-return',
                "Returned type is less specific due to nested 'mixed'."
            ) => 1,
            BaselineIssue::makeKey('classes/C.php', 'possibly-null', 'Value "x" may be null \\ here é') => 3,
        ], $baseline->counts());
        self::assertSame(6, $baseline->issueCount());
    }

    #[Test]
    public function should_round_trip_through_its_own_toml(): void
    {
        $original = LooseBaseline::fromIssues([
            new BaselineIssue('b.php', 'Code.B', "Tab\tand \"quote\" and \\ slash"),
            new BaselineIssue('a.php', 'Code.A', 'It\'s fine'),
            new BaselineIssue('a.php', 'Code.A', 'It\'s fine'),
            new BaselineIssue('a.php', 'Code.C', "Bell \x07 char"),
        ]);

        $toml = $original->toToml();

        $expected = $original->counts();
        ksort($expected);
        self::assertSame($expected, LooseBaseline::fromToml($toml)->counts());
        self::assertStringStartsWith("variant = \"loose\"\n\n[[issues]]\nfile = \"a.php\"\ncode = \"Code.A\"", $toml);
    }

    #[Test]
    public function should_allow_issues_up_to_the_baselined_count(): void
    {
        $baseline = LooseBaseline::fromIssues([
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('a.php', 'X', 'm'),
        ]);
        $current = [new BaselineIssue('a.php', 'X', 'm'), new BaselineIssue('a.php', 'X', 'm')];

        self::assertSame([], $baseline->unmatched($current));
    }

    #[Test]
    public function should_report_every_occurrence_when_a_count_is_exceeded(): void
    {
        $baseline = LooseBaseline::fromIssues([new BaselineIssue('a.php', 'X', 'm')]);
        $first = new BaselineIssue('a.php', 'X', 'm');
        $second = new BaselineIssue('a.php', 'X', 'm');
        $unrelated = new BaselineIssue('b.php', 'X', 'm');

        self::assertSame([$first, $second, $unrelated], $baseline->unmatched([$first, $second, $unrelated]));
    }

    #[Test]
    public function should_count_entries_that_no_longer_fully_occur(): void
    {
        $baseline = LooseBaseline::fromIssues([
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('b.php', 'Y', 'n'),
            new BaselineIssue('c.php', 'Z', 'o'),
        ]);

        self::assertSame(2, $baseline->outdatedEntryCount([
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('c.php', 'Z', 'o'),
        ]));
    }

    #[Test]
    public function should_reject_a_strict_variant(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LooseBaseline::fromToml("variant = \"strict\"\n");
    }

    #[Test]
    public function should_reject_an_entry_missing_its_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LooseBaseline::fromToml("variant = \"loose\"\n\n[[issues]]\nfile = \"a.php\"\ncode = \"X\"\ncount = 1\n");
    }
}
