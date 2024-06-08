<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

class Arrays
{
    /**
     * Similar to `is_iterable` from PHP-7.1.0+
     *
     * @param mixed $obj
     * @return bool
     */
    public static function iterable(&$obj)
    {
        return is_array($obj) || $obj instanceof \Traversable;
    }

    /**
     * Check if array is indexed, like ['foo', 'bar']. Similar to `array_is_list`
     *
     * @param mixed $array
     * @return bool
     */
    public static function indexed(array &$array)
    {
        $index = 0;

        foreach ($array as $key => $value) {
            if ($key !== $index++) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if array is associative, like ['bar' => foo', 'baz' => 'bar']
     *
     * @param mixed $array
     * @return bool
     */
    public static function associative(array &$array)
    {
        return self::indexed($array) === false;
    }

    /**
     * Ksort recursive
     *
     * @param array $array
     * @param int   $flags See details in https://www.php.net/manual/en/function.ksort.php#refsect1-function.ksort-parameters
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
