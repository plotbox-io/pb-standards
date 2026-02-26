<?php declare(strict_types=1);

namespace PlotBox\Standards\Tests\Sniffs\Functions;

use PHPUnit\Framework\Attributes\Test;
use PlotBox\Standards\Tests\SniffTestCase;

final class RequireSafeFunctionsSniffTest extends SniffTestCase
{
    protected function sniffName(): string
    {
        return 'PlotBox.Functions.RequireSafeFunctions';
    }

    #[Test]
    public function should_flag_native_function_calls_with_safe_equivalents(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertContains(14, $errorLines, 'json_encode() without Safe import should be flagged');
        self::assertContains(55, $errorLines, 'preg_match() without Safe import should be flagged');
        self::assertContains(67, $errorLines, 'file_get_contents() without Safe import should be flagged');
    }

    #[Test]
    public function should_flag_calls_inside_closures_and_arrow_functions(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertContains(74, $errorLines, 'json_encode() inside closure should be flagged');
        self::assertContains(81, $errorLines, 'json_encode() inside arrow function should be flagged');
    }

    #[Test]
    public function should_flag_first_class_callable_syntax(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertContains(87, $errorLines, 'json_encode(...) first-class callable should be flagged');
    }

    #[Test]
    public function should_not_flag_functions_already_imported_via_safe(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertNotContains(20, $errorLines, 'json_decode() with Safe import should not be flagged');
    }

    #[Test]
    public function should_not_flag_method_calls(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertNotContains(26, $errorLines, 'Instance method call should not be flagged');
        self::assertNotContains(32, $errorLines, 'Static method call should not be flagged');
        self::assertNotContains(49, $errorLines, 'Nullsafe method call should not be flagged');
    }

    #[Test]
    public function should_not_flag_fully_qualified_calls(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertNotContains(38, $errorLines, 'Fully qualified \\json_encode() should not be flagged');
    }

    #[Test]
    public function should_not_flag_function_definitions(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertNotContains(42, $errorLines, 'Function definition named json_encode should not be flagged');
    }

    #[Test]
    public function should_not_flag_string_literals(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertNotContains(61, $errorLines, 'String literal containing function name should not be flagged');
    }

    #[Test]
    public function should_only_produce_expected_errors(): void
    {
        $errorLines = $this->errorLines($this->fixturePath('RequireSafeFunctions.php'));

        self::assertSame([14, 55, 67, 74, 81, 87], $errorLines);
    }
}
