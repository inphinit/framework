<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Environment;

use Inphinit\Exception;

class Env
{
    const REGEX_FLOAT = '/^-?(0|[1-9]\d*)\.\d+$/';
    const REGEX_INT = '/^-?(0|[1-9]\d*)$/';

    /**
     * Get value from `$_ENV[...]`. If not exists or empty string return aternate value
     *
     * @param string $name
     * @param mixed  $alternative
     * @return mixed
     */
    public static function entry($name, $alternative = null)
    {
        return isset($_ENV[$name]) || $_ENV[$name] !== '' ? $_ENV[$name] : $alternative;
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

        if ($value === '0' || $value === 'false' || $value === 'no') {
            return false;
        }

        if ($value === '1' || $value === 'true' || $value === 'yes') {
            return true;
        }

        throw new Exception("Can not convert {$name}={$value} to boolean");
    }

    /**
     * Get value from `$_ENV[...]` as float
     *
     * @param string $name
     * @return bool
     */
    public static function float($name, $alternative = 0.0)
    {
        $value = self::entry($name, $alternative);

        if ($value === $alternative) {
            return $value;
        }

        if (preg_match(self::REGEX_FLOAT, $value) === 1) {
            return floatval($value);
        }

        throw new Exception("Can not convert {$name}={$value} to float");
    }

    /**
     * Get value from `$_ENV[...]` as integer
     *
     * @param string $name
     * @return bool
     */
    public static function int($name, $alternative = 0)
    {
        $value = self::entry($name, $alternative);

        if ($value === $alternative) {
            return $value;
        }

        if (preg_match(self::REGEX_INT, $value) === 1) {
            return intval($value);
        }

        throw new Exception("Can not convert {$name}={$value} to int");
    }
}
