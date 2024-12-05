<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

class Strings
{
    /**
     * Convert string to ASCII
     *
     * @param string $text
     * @return string
     */
    public static function toAscii($text, array $encodings = array())
    {
        if (empty($encodings)) {
            $encodings = \mb_detect_order();
        }

        $encode = \mb_detect_encoding($text, $encodings, true);

        return 'ASCII' === $encode ? $text : \iconv($encode, 'ASCII//TRANSLIT//IGNORE', $text);
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
