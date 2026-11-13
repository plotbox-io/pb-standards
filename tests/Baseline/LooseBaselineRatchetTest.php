<?php

declare(strict_types=1);

namespace PlotBox\Standards\Tests\Baseline;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PlotBox\Standards\Baseline\BaselineIssue;
use PlotBox\Standards\Baseline\LooseBaseline;
use PlotBox\Standards\Baseline\LooseBaselineRatchet;

final class LooseBaselineRatchetTest extends TestCase
{
    #[Test]
    public function should_allow_removed_entries_and_lower_counts(): void
    {
        $before = LooseBaseline::fromIssues([
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('b.php', 'Y', 'n'),
        ]);
        $after = LooseBaseline::fromIssues([new BaselineIssue('a.php', 'X', 'm')]);

        self::assertSame([], (new LooseBaselineRatchet())->violations($before, $after));
    }

    #[Test]
    public function should_allow_entries_to_follow_a_renamed_file(): void
    {
        $before = LooseBaseline::fromIssues([new BaselineIssue('old/A.php', 'X', 'm')]);
        $after = LooseBaseline::fromIssues([new BaselineIssue('new/A.php', 'X', 'm')]);

        self::assertSame(
            [],
            (new LooseBaselineRatchet())->violations($before, $after, ['old/A.php' => 'new/A.php'])
        );
    }

    #[Test]
    public function should_flag_new_entries_and_raised_counts(): void
    {
        $before = LooseBaseline::fromIssues([new BaselineIssue('a.php', 'X', 'm')]);
        $after = LooseBaseline::fromIssues([
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('a.php', 'X', 'm'),
            new BaselineIssue('moved/A.php', 'X', 'm'),
        ]);

        self::assertSame(
            [
                'Count raised from 1 to 2: a.php [X] m',
                'New entry: moved/A.php [X] m',
            ],
            (new LooseBaselineRatchet())->violations($before, $after)
        );
    }
}
