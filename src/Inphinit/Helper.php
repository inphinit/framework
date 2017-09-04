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
     * @param string             $path
     * @param array|\Traversable $items
     * @return mixed
     */
    public static function extract($path, $items)
    {
        $paths = explode('.', $path);

        foreach ($paths as $value) {
            if (self::iterable($items) && array_key_exists($value, $items)) {
                $items = $items[$value];
            } else {
                $items = false;
                break;
            }
        }

        return $items;
    }

    /**
     * Equivalent to `is_iterable` from PHP-7.1.0+
     *
     * @param mixed $obj
     * @return bool
     */
    public static function iterable($obj)
    {
        return is_array($obj) || $obj instanceof \Traversable;
    }

    /**
     * Check if array is sequential, like ['foo', 'bar']
     *
     * @param mixed $obj
     * @return bool
     */
    public static function seq($array)
    {
        if (is_array($array)) {
            $k = array_keys($array);
            return $k === array_keys($k);
        } else {
            return false;
        }
    }

    /**
     * Check if array is associative, like [ 'bar' => foo', 'baz' => 'bar']
     *
     * @param mixed $obj
     * @return bool
     */
    public static function assoc($array)
    {
        return false === self::seq($array);
    }
}
