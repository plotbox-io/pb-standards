<?php

declare(strict_types=1);

namespace PlotBox\Standards\Tests\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PlotBox\Standards\Command\PhpcsStandard;

final class PhpcsStandardTest extends TestCase
{
    #[Test]
    public function should_default_to_plotbox(): void
    {
        self::assertSame('PlotBox', PhpcsStandard::resolve(null, null));
        self::assertSame('PlotBox', PhpcsStandard::resolve('', ''));
    }

    #[Test]
    public function should_prefer_injected_phpcs_config_path(): void
    {
        self::assertSame(
            'phpcs.project.xml',
            PhpcsStandard::resolve(null, 'phpcs.project.xml')
        );
    }

    #[Test]
    public function should_prefer_cli_standard_over_injected_path(): void
    {
        self::assertSame(
            'Laravel',
            PhpcsStandard::resolve('Laravel', 'phpcs.project.xml')
        );
    }
}
