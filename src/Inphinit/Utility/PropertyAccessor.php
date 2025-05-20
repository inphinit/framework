<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

class PropertyAccessor
{
    /**
     * Read array or object by path using dot
     *
     * @param string          $path
     * @param array|\stdClass $items
     * @param mixed           $alternative
     * @return mixed
     */
    public static function getValue($path, $items, $alternative = null)
    {
        foreach (explode('.', $path) as $key) {
            if (is_array($items) && isset($items[$key])) {
                $items = $items[$key];
            } elseif (is_object($items) && isset($items->{$key})) {
                $items = $items->{$key};
            } else {
                return $alternative;
            }
        }

        return $items;
    }
}
