<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Environment;

use Inphinit\Exception;

class Env
{
    /**
     * Get value from `$_ENV[...]`. If not exists or empty string return aternate value
     *
     * @param string $name
     * @param mixed  $alternative
     * @return mixed
     */
    public static function entry($name, $alternative = null)
    {
        return isset($_ENV[$name]) && $_ENV[$name] !== '' ? $_ENV[$name] : $alternative;
    }

    /**
     * Get value from `$_ENV[...]` as boolean
     *
     * @param string $name
     * @param mixed  $alternative
     * @return bool
     */
    public static function bool($name, $alternative = false)
    {
        $value = self::entry($name, $alternative);

        if ($value === $alternative) {
            return $value;
        }

        $lValue = strtolower($value);

        if ($lValue === '0' || $lValue === 'false' || $lValue === 'no') {
            return false;
        }

        if ($lValue === '1' || $lValue === 'true' || $lValue === 'yes') {
            return true;
        }

        throw new Exception("Can not convert {$name}={$value} to boolean");
    }

    /**
     * Get value from `$_ENV[...]` as float
     *
     * @param string $name
     * @return float
     */
    public static function float($name, $alternative = 0.0)
    {
        $value = self::entry($name, $alternative);

        if ($value === $alternative) {
            return $value;
        }

        if (is_numeric($value)) {
            return floatval($value);
        }

        throw new Exception("Can not convert {$name}={$value} to float");
    }

    /**
     * Get value from `$_ENV[...]` as integer
     *
     * @param string $name
     * @return int
     */
    public static function int($name, $alternative = 0)
    {
        $value = self::entry($name, $alternative);

        if ($value === $alternative) {
            return $value;
        }

        if (is_numeric($value)) {
            return intval($value, 10);
        }

        throw new Exception("Can not convert {$name}={$value} to int");
    }
}
