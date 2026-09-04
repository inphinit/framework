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
     * @param array &$info
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
     * @param string &$file
     * @param int    &$line
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

    /**
     * Utility to check if a regex expression contains issues
     *
     * @param string      $expression
     * @param string|null &$errorMessage
     * @param int|null    &$errorCode
     * @return bool
     */
    public static function regex($expression, &$errorMessage, &$errorCode)
    {
        $errorCode = 0;

        if (is_string($expression) === false) {
            $type = self::type($expression);
            $errorMessage = "Expects to be string, {$type} given";
            return false;
        } elseif (preg_match($expression, 'sample sample sample') !== false) {
            $errorMessage = null;
            $errorCode = null;
            return true;
        }

        $prepend_message = 'The expression "' . $expression . '" has errors';
        $errorCode = preg_last_error();

        if (function_exists('preg_last_error_msg')) {
            $errorMessage = $prepend_message . ': ' . preg_last_error_msg();
            return false;
        }

        $message = 'Unknown error';

        switch ($errorCode) {
            case PREG_INTERNAL_ERROR:
                $message = 'Internal error';
                break;
            case PREG_BACKTRACK_LIMIT_ERROR:
                $message = 'Backtrack limit exhausted';
                break;
            case PREG_RECURSION_LIMIT_ERROR:
                $message = 'Recursion limit exhausted';
                break;
            case PREG_BAD_UTF8_ERROR:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            case PREG_BAD_UTF8_OFFSET_ERROR:
                $message = 'The offset did not correspond to the beginning of a valid UTF-8 code point';
                break;
            default:
                if (defined('PREG_JIT_STACKLIMIT_ERROR') && PREG_JIT_STACKLIMIT_ERROR === $error) {
                    $message = 'JIT stack limit exhausted';
                }
        }

        $errorMessage = $prepend_message . ': ' . $message;
        return false;
    }
}
