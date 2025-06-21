<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

class Strings
{
    private static $tAscii;

    /**
     * Convert string to ASCII
     *
     * @param string $text
     * @return string
     */
    public static function toAscii($text)
    {
        if (self::$tAscii === null) {
            self::$tAscii = \Transliterator::create('Any-Latin; Latin-ASCII; [:^ASCII:] Remove');
        }

        return self::$tAscii->transliterate($text);
    }

    /**
     * Capitalize words using hyphen or a custom delimiter.
     *
     * @param string $text
     * @param string $delimiter
     * @param string $glue
     * @return string
     */
    public static function capitalize($text, $delimiter = '-', $glue = '')
    {
        return implode($glue, array_map('ucfirst', explode($delimiter, strtolower($text))));
    }
}
