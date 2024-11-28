<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Utility;

use Inphinit\Exception;

class ObjectPath
{
    private $data;
    private $isArray = false;

    /**
     * Define object or array
     *
     * @param array|object $source
     */
    public function __construct(&$source)
    {
        if (is_object($source) || ($this->isArray = is_array($source))) {
            $this->data = $source;
        } else {
            throw new Exception('Expected an array or object');
        }
    }

    /**
     * Get item
     *
     * @return mixed
     */
    public function __get($key)
    {
        if (strpos($key, '$.') !== 0) {
            if ($this->isArray) {
                return isset($this->data[$key]) ? $this->data[$key] : null;
            }

            return isset($this->data->{$key}) ? $this->data->{$key} : null;
        }

        $found = true;
        $items = &$this->data;

        foreach (self::path($key) as $path) {
            if (is_array($items) && isset($items[$path])) {
                $items = &$items[$path];
            } elseif (is_object($items) && isset($items->{$path})) {
                $items = &$items->{$path};
            } else {
                $found = false;
                break;
            }
        }

        return $found ? $items : null;
    }

    /**
     * Set item
     */
    public function __set($key, $value)
    {
        $items = &$this->data;
        $isArray = $this->isArray;

        if (strpos($key, '$.') === 0) {

            $path = self::path($key);
            $last = array_pop($path);

            foreach ($path as $value) {
                if (is_array($items) && array_key_exists($value, $items)) {
                    $items = &$items[$value];
                    $isArray = true;
                } elseif (is_object($items) && property_exists($items, $value)) {
                    $items = &$items->{$value};
                    $isArray = false;
                } else {
                    $items = $isArray ? array() : new \stdClass();
                    $items = &$items;
                }
            }
        } else {
            $last = $key;
        }

        if ($isArray) {
            $items[$last] = $value;
        } else {
            $items->{$last} = $value;
        }
    }

    private static function path($path)
    {
        return explode('.', substr($path, 2));
    }
}
