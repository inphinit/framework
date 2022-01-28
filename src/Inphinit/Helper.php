<?php
/*
 * Inphinit
 *
 * Copyright (c) 2022 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
     * @return stdClass|null
     */
    public static function parseVersion($version)
    {
        //if (preg_match('#^(\d+)\.(\d+)\.(\d+)(-([\da-z]+(\.[\da-z]+)*)(\+([\da-z]+(\.[\da-z]+)*))?)?$#', $version, $matches)) {

        if (preg_match('#^(\d|[1-9]\d+)\.(\d|[1-9]\d+)\.(\d|[1-9]\d+)((?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?)$#', $version, $matches)) {
            $matches = array(
                'major' => $matches[1],
                'minor' => $matches[2],
                'patch' => $matches[3],
                'extra' => isset($matches[4]) ? $matches[4] : null,
                'release' => isset($matches[5]) && $matches[5] !== '' ? explode('.', $matches[5]) : null,
                'build' => isset($matches[6]) && $matches[6] !== '' ? explode('.', $matches[6]) : null
            );

            return (object) $matches;
        }

        return null;
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
     * @param string $text
     * @param string $delimiter
     * @param string $glue
     * @return string
     */
    public static function capitalize($text, $delimiter = '-', $glue = '')
    {
        return implode($glue, array_map('ucfirst', explode($delimiter, strtolower($text))));
    }

    /**
     * Read array or object by path using dot
     *
     * @param string         $path
     * @param array|stdClass $items
     * @param mixed          $alternative
     * @return mixed
     */
    public static function extract($path, $items, $alternative = null)
    {
        $paths = explode('.', $path);

        foreach ($paths as $value) {
            if (is_array($items) && array_key_exists($value, $items)) {
                $items = $items[$value];
            } elseif (is_object($items) && property_exists($items, $value)) {
                $items = $items->$value;
            } else {
                return $alternative;
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
     * @param mixed $array
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
     * @param mixed $array
     * @return bool
     */
    public static function assoc($array)
    {
        return false === self::seq($array);
    }

    /**
     * Check if array is associative, like [ 'bar' => foo', 'baz' => 'bar']
     *
     * @param array $array
     * @param int   $flags See details in https://www.php.net/manual/en/function.sort.php#refsect1-function.sort-parameters
     * @return void
     */
    public static function ksort(array &$array, $flags = \SORT_REGULAR)
    {
        foreach ($array as &$item) {
            if (is_array($item)) {
                self::ksort($item, $flags);
            }
        }

        ksort($array, $flags);
    }
}
