<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Diagnostics;

class Inspector
{
    /**
     * Get caller from backtrace php scripts
     *
     * @param int   $level
     * @param array $info
     * @param int   $limit
     * @return bool
     */
    public static function caller($level, &$info, $limit = 100)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

        if (isset($trace[$level]['file'], $trace[$level]['line'])) {
            $info = $trace[$level];

            self::evalSource($info['file'], $info['file'], $info['line']);

            return true;
        }

        return false;
    }

    /**
     * Identify and get the possible source of an error message caused by `eval()`
     *
     * @param string $message
     * @param string $file
     * @param int    $line
     * @return bool
     */
    public static function evalSource($message, &$file, &$line)
    {
        $message = trim($message);

        if (preg_match('#(.*)\((\d+)\)\s+:\s+eval\(\)\'d\s+code(\s+on\s+line\s+\d+)?$#', $message, $match)) {
            $file = $match[1];
            $line = (int) $match[2];

            return true;
        }

        return false;
    }

    /**
     * Gets the type name of a variable with `get_debug_type()` when available; otherwise, falls back to `gettype()`
     *
     * @param mixed $value
     * @return string
     */
    public static function type($value)
    {
        return function_exists('get_debug_type') ? get_debug_type($value) : gettype($value);
    }
}
