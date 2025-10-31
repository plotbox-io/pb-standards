<?php

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName

namespace PlotBox\Standards\Util;

use InvalidArgumentException;
use Stringable;

class StringHelper
{
    /**
     * @param string $input
     * @param int $pad_length
     * @param string $pad_string
     * @param int $pad_type
     * @param string $encoding
     * @return string
     * @see https://stackoverflow.com/a/14773638
     */
    public static function mb_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT, $encoding = 'UTF-8')
    {
        $input_length = mb_strlen($input, $encoding);
        $pad_string_length = mb_strlen($pad_string, $encoding);

        if ($pad_length <= 0 || ($pad_length - $input_length) <= 0) {
            return $input;
        }

        $num_pad_chars = $pad_length - $input_length;

        switch ($pad_type) {
            case STR_PAD_RIGHT:
                $left_pad = 0;
                $right_pad = $num_pad_chars;
                break;

            case STR_PAD_LEFT:
                $left_pad = $num_pad_chars;
                $right_pad = 0;
                break;

            case STR_PAD_BOTH:
                $left_pad = floor($num_pad_chars / 2);
                $right_pad = $num_pad_chars - $left_pad;
                break;
            default:
                throw new InvalidArgumentException();
        }

        $result = '';
        for ($i = 0; $i < $left_pad; $i++) {
            $result .= mb_substr($pad_string, $i % $pad_string_length, 1, $encoding);
        }
        $result .= $input;
        for ($i = 0; $i < $right_pad; $i++) {
            $result .= mb_substr($pad_string, $i % $pad_string_length, 1, $encoding);
        }

        return $result;
    }

    public static function removeFromStart(string $needle, string $haystack): string
    {
        if (str_starts_with($haystack, $needle)) {
            return substr($haystack, strlen($needle));
        }

        return $haystack;
    }

    /** @see https://stackoverflow.com/a/27368848 */
    public static function canBeCastToString(mixed $var): bool
    {
        return $var === null || is_scalar($var) || $var instanceof Stringable;
    }
}
