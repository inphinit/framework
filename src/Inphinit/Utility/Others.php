<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

class Others
{
    /**
     * Read array or object by path using dot
     *
     * @param string          $path
     * @param array|\stdClass $items
     * @param mixed           $alternative
     * @return mixed
     */
    public static function extract($path, &$items, $alternative = null)
    {
        foreach (explode('.', $path) as $value) {
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
}
