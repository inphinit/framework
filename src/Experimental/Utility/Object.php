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

class Object
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
        if (strpos($key, '$.') === false) {
            if ($this->isArray) {
                return isset($this->data[$key]) ? $this->data[$key] : null;
            }

            return isset($this->data->$key) ? $this->data->$key : null;
        }

        $found = true;
        $items = &$this->data;

        foreach (self::path($key) as $path) {
            if (is_array($items) && isset($items[$path])) {
                $items = &$items[$path];
            } elseif (is_object($items) && isset($items->$path)) {
                $items = &$items->$path;
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
        if (strpos($key, '$.') === false) {
            if ($this->isArray) {
                if (isset($this->data[$key])) {
                    $this->data[$key] = $value;
                }
            } elseif (isset($this->data->$key)) {
                $this->data->$key = $value;
            }
        } else {
            $found = true;
            $items = &$this->data;

            foreach (self::path($key) as $path) {
                if (is_array($items) && isset($items[$path])) {
                    $items = &$items[$path];
                } elseif (is_object($items) && isset($items->$path)) {
                    $items = &$items->$path;
                } else {
                    $found = false;
                    break;
                }
            }

            if ($found) {
                $items = $value;
            }
        }
    }

    private static function path($path)
    {
        return explode('.', substr($path, 2));
    }
}
