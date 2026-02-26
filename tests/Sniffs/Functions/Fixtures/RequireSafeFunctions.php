<?php

declare(strict_types=1);

namespace App\Test;

use function Safe\json_decode;

class RequireSafeFunctionsFixture
{
    // Line 12: SHOULD flag — native json_encode has Safe equivalent
    public function nativeJsonEncode(): string
    {
        return json_encode(['key' => 'value']);
    }

    // Line 18: SHOULD NOT flag — imported via Safe
    public function safeJsonDecode(): array
    {
        return json_decode('{}', true);
    }

    // Line 24: SHOULD NOT flag — instance method call
    public function methodCall(): void
    {
        $this->json_encode();
    }

    // Line 30: SHOULD NOT flag — static method call
    public function staticCall(): void
    {
        SomeClass::json_encode();
    }

    // Line 36: SHOULD NOT flag — fully qualified (preceded by NS separator)
    public function fullyQualified(): void
    {
        \json_encode([]);
    }

    // Line 41: SHOULD NOT flag — function definition with matching name
    public function json_encode(): void
    {
    }

    // Line 46: SHOULD NOT flag — nullsafe method call
    public function nullsafeCall(?self $obj): void
    {
        $obj?->json_encode();
    }

    // Line 52: SHOULD flag — preg_match has Safe equivalent
    public function nativePreg(): int
    {
        return preg_match('/test/', 'test string');
    }

    // Line 58: SHOULD NOT flag — string literal, not a function call
    public function stringArg(): void
    {
        $fn = 'json_encode';
    }

    // Line 63: SHOULD flag — file_get_contents has Safe equivalent
    public function fileRead(): string
    {
        return file_get_contents('/tmp/test');
    }

    // Line 69: SHOULD flag — json_encode inside closure
    public function closureCall(): void
    {
        $fn = function () {
            return json_encode([]);
        };
    }

    // Line 75: SHOULD flag — json_encode inside arrow function
    public function arrowFn(): void
    {
        $fn = fn () => json_encode([]);
    }

    // Line 80: SHOULD flag — first-class callable syntax
    public function firstClassCallable(): \Closure
    {
        return json_encode(...);
    }
}
