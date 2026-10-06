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
     * @param string|null $alternative
     * @return mixed
     */
    public static function entry($name, $alternative = null)
    {
        return isset($_ENV[$name][0]) ? $_ENV[$name] : $alternative;
    }

    /**
     * Get value from `$_ENV[...]` as boolean
     *
     * - Returns bool(true) for '1', 'true', 'on', and 'yes'.
     * - Returns bool(false) for '0', 'false', 'off', and 'no'.
     *
     * @param string $name
     * @param bool $alternative
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function bool($name, $alternative = false)
    {
        $value = static::entry($name);

        if ($value === null) {
            return $alternative;
        }

        if ($value !== '' && trim($value) === $value) {
            $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($result !== null) {
                return $result;
            }
        }

        throw new Exception("Cannot convert {$name}={$value} to boolean");
    }

    /**
     * Get value from `$_ENV[...]` as float
     *
     * @param string $name
     * @param float $alternative
     * @throws \Inphinit\Exception
     * @return float
     */
    public static function float($name, $alternative = 0.0)
    {
        $value = static::entry($name);

        if ($value === null) {
            return $alternative;
        }

        if (trim($value) === $value) {
            $result = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($result !== false && is_finite($result)) {
                return $result;
            }
        }

        throw new Exception("Cannot convert {$name}={$value} to float");
    }

    /**
     * Get value from `$_ENV[...]` as integer
     *
     * @param string $name
     * @param int $alternative
     * @throws \Inphinit\Exception
     * @return int
     */
    public static function int($name, $alternative = 0)
    {
        $value = static::entry($name);

        if ($value === null) {
            return $alternative;
        }

        if (trim($value) === $value) {
            $result = filter_var($value, FILTER_VALIDATE_INT);

            if ($result !== false) {
                return $result;
            }
        }

        throw new Exception("Cannot convert {$name}={$value} to int");
    }
}
