<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Helper
{
    /**
     * Parse version format
     *
     * @param string $version
     * @return array|bool
     */
    public static function parseVersion($version)
    {
        if (preg_match('#^(\d+)\.(\d+)\.(\d+)(\-([\w.\-]+)|)$#', $version, $match)) {
            return (object) array(
                'major' => $match[1],
                'minor' => $match[2],
                'patch' => $match[3],
                'extra' => empty($match[5]) ? null : $match[5]
            );
        }

        return false;
    }

    /**
     * Convert string to ASCII
     *
     * @param string $text
     * @return string
     */
    public static function toAscii($text)
    {
        $encode = mb_detect_encoding($text, mb_detect_order(), true);
        return 'ASCII' === $encode ? $text : iconv($encode, 'ASCII//TRANSLIT//IGNORE', $text);
    }

    /**
     * Capitalize words using hyphen or a custom delimiter.
     *
     * @param string $str
     * @param string $delimiter
     * @param string $glue
     * @return string
     */
    public static function capitalize($str, $delimiter = '-', $glue = '')
    {
        return implode($glue, array_map('ucfirst', explode($delimiter, strtolower($str))));
    }

    /**
     * Read array by path using dot
     *
     * @param string $path
     * @param array  $items
     * @return mixed
     */
    public static function arrayPath($path, array $items)
    {
        $paths = explode('.', $path);

        foreach ($paths as $value) {
            $items = is_array($items) && array_key_exists($value, $items) ? $items[$value] : false;

            if ($items === false) {
                break;
            }
        }

        return $items;
    }
}
