<?php declare(strict_types=1);

namespace PlotBox\Standards\Tests\Sniffs\Namespaces;

use PHPUnit\Framework\Attributes\Test;
use PlotBox\Standards\Tests\SniffTestCase;

final class DisallowGlobalFunctionImportSniffTest extends SniffTestCase
{
    protected function sniffName(): string
    {
        return 'PlotBox.Namespaces.DisallowGlobalFunctionImport';
    }

    #[Test]
    public function should_flag_bare_global_function_imports(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('DisallowGlobalFunctionImport.php'));

        self::assertContains(10, $errorLines, 'use function strlen should be flagged');
        self::assertContains(11, $errorLines, 'use function array_map should be flagged');
        self::assertContains(12, $errorLines, 'use function count should be flagged');
    }

    #[Test]
    public function should_not_flag_safe_namespace_imports(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('DisallowGlobalFunctionImport.php'));

        self::assertNotContains(8, $errorLines, 'use function Safe\\json_decode should not be flagged');
        self::assertNotContains(9, $errorLines, 'use function Safe\\file_get_contents should not be flagged');
    }

    #[Test]
    public function should_not_flag_namespaced_function_imports(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('DisallowGlobalFunctionImport.php'));

        self::assertNotContains(13, $errorLines, 'use function App\\Helpers\\custom_function should not be flagged');
    }

    #[Test]
    public function should_not_flag_class_imports_or_const_imports(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('DisallowGlobalFunctionImport.php'));

        self::assertNotContains(7, $errorLines, 'use SomeClass should not be flagged');
        self::assertNotContains(14, $errorLines, 'use const PHP_EOL should not be flagged');
    }

    #[Test]
    public function should_only_produce_expected_errors(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('DisallowGlobalFunctionImport.php'));

        self::assertSame([10, 11, 12], $errorLines);
    }

    #[Test]
    public function should_fix_by_removing_bare_global_function_imports(): void
    {
        $fixedContent = $this->fixFixture($this->fixturePath('DisallowGlobalFunctionImport.php'));
        $expectedContent = file_get_contents($this->fixturePath('DisallowGlobalFunctionImport.fixed.php'));

        self::assertSame($expectedContent, $fixedContent);
    }
}
