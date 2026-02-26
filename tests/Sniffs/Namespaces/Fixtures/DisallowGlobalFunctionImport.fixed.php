<?php

declare(strict_types=1);

namespace App\Test;

use SomeClass;
use function Safe\json_decode;
use function Safe\file_get_contents;
use function App\Helpers\custom_function;
use const PHP_EOL;

class DisallowGlobalFunctionImportFixture
{
    public function example(): void
    {
        $len = strlen('test');
        $mapped = array_map('intval', ['1']);
        $c = count([1, 2, 3]);
        custom_function();
        $decoded = json_decode('{}');
        $content = file_get_contents('/tmp/test');
    }
}
