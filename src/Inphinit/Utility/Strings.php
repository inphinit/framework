<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

class Strings
{
    private static $onlyAscii;

    /**
     * Convert string to ASCII
     *
     * @param string $text
     * @return string
     */
    public static function ascii($text)
    {
        if (self::$onlyAscii === null) {
            self::$onlyAscii = \Transliterator::create('Any-Latin; Latin-ASCII; [:^ASCII:] Remove');
        }

        return self::$onlyAscii->transliterate($text);
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
