<?php

declare(strict_types=1);

namespace PlotBox\Standards\Util;

final class Util
{
    public static function thumbsUpAscii(): string
    {
        return <<<EOL
░░░░░░░░░░░░▄▄░░░░░░░░░
░░░░░░░░░░░█░░█░░░░░░░░
░░░░░░░░░░░█░░█░░░░░░░░
░░░░░░░░░░█░░░█░░░░░░░░
░░░░░░░░░█░░░░█░░░░░░░░
███████▄▄█░░░░░██████▄░
▓▓▓▓▓▓█░░░░░░░░░░░░░░█░
▓▓▓▓▓▓█░░░░░░░░░░░░░░█░
▓▓▓▓▓▓█░░░░░░░░░░░░░░█░
▓▓▓▓▓▓█░░░░░░░░░░░░░░█░
▓▓▓▓▓▓█░░░░░░░░░░░░░░█░
▓▓▓▓▓▓█████░░░░░░░░░█░░
██████▀░░░░▀▀██████▀░░░
EOL;
    }

    /** @psalm-suppress ForbiddenCode */
    private static function privilegedFileExists(string $filePath): bool
    {
        $command = "sudo [ -f '$filePath' ] && echo 1 || echo 0";
        $result = trim((string) shell_exec($command));
        return (bool) $result;
    }

    public static function makeConsoleLink(string $link, string $text): string
    {
        return "\e]8;;" . $link . "\e\\" . $text . "\e]8;;\e\\";
    }

    /**
     * Take an internal container file path and format it as a link relative
     * to the app root on the developer host
     */
    public static function makeConsoleLinkFromPath(
        string $filePath,
        int $line,
        string $realAppRoot,
        ?int $maxWidth = null
    ): string {
        $relativePath = StringHelper::removeFromStart('/app/', $filePath);
        $vueSrcRelativePath = $filePath;
        $uiPathPrefixesToTrim = ['vue2/src/', 'classes/'];
        foreach ($uiPathPrefixesToTrim as $uiPathPrefixToTrim) {
            $vueSrcRelativePath = StringHelper::removeFromStart($uiPathPrefixToTrim, $vueSrcRelativePath);
        }
        $linkText = $vueSrcRelativePath . ':' . $line;
        if ($maxWidth !== null) {
            $linkText = self::elipsesString(
                $linkText,
                $maxWidth
            );
        }

        return self::makeConsoleLink(
            'file://' . $realAppRoot . '/' . $relativePath . ':' . $line,
            $linkText
        );
    }

    public static function elipsesString(
        string $text,
        int $maxChars
    ): string {
        return strlen($text) > $maxChars
            ? substr($text, 0, $maxChars) . '...'
            : $text;
    }
}
