<?php declare(strict_types=1);

namespace PlotBox\Standards\Tests;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Ruleset;
use PHPUnit\Framework\TestCase;

abstract class SniffTestCase extends TestCase
{
    abstract protected function sniffName(): string;

    /** @return array{errors: array<int, list<array{message: string, source: string}>>, warnings: array<int, list<array{message: string, source: string}>>} */
    protected function lintFixture(string $fixturePath): array
    {
        $config = new Config();
        $config->standards = ['PlotBox'];
        $config->sniffs = [$this->sniffName()];

        $ruleset = new Ruleset($config);
        $file = new LocalFile($fixturePath, $ruleset, $config);
        $file->process();

        return [
            'errors' => $file->getErrors(),
            'warnings' => $file->getWarnings(),
        ];
    }

    /** @return list<int> */
    protected function errorLines(string $fixturePath): array
    {
        $result = $this->lintFixture($fixturePath);
        return array_keys($result['errors']);
    }

    protected function fixFixture(string $fixturePath): string
    {
        $config = new Config();
        $config->standards = ['PlotBox'];
        $config->sniffs = [$this->sniffName()];

        $ruleset = new Ruleset($config);
        $file = new LocalFile($fixturePath, $ruleset, $config);
        $file->process();

        $file->fixer->fixFile();
        return $file->fixer->getContents();
    }

    protected function fixturePath(string $filename): string
    {
        $reflection = new \ReflectionClass(static::class);
        return dirname($reflection->getFileName()) . '/Fixtures/' . $filename;
    }
}
