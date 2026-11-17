<?php

declare(strict_types=1);

namespace PlotBox\Standards\Command;

final class PhpcsStandard
{
    public const DEFAULT = 'PlotBox';

    public static function resolve(?string $cliStandard, ?string $phpcsConfigPath): string
    {
        if (is_string($cliStandard) && $cliStandard !== '') {
            return $cliStandard;
        }
        if (is_string($phpcsConfigPath) && $phpcsConfigPath !== '') {
            return $phpcsConfigPath;
        }
        return self::DEFAULT;
    }
}
