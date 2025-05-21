<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

class Inspector
{
    /**
     * Get backtrace php scripts
     *
     * @param int $level
     * @param int $limit
     * @return array
     */
    public static function caller($level = 0, $limit = 100)
    {
        $trace = debug_backtrace(0, $limit);

        foreach ($trace as $key => &$value) {
            if (isset($value['file'])) {
                self::evalSource($value['file'], $value['file'], $value['line']);
            } else {
                unset($trace[$key]);
            }
        }

        $trace = array_values($trace);

        if ($level < 0) {
            return $trace;
        } elseif (isset($trace[$level])) {
            return $trace[$level];
        }

        return array();
    }

    /**
     * Identify and get the possible source of an error message caused by eval()
     *
     * @param int $level
     * @param int $limit
     * @return array
     */
    public static function evalSource($message, &$file, &$line)
    {
        $message = trim($message);

        if (preg_match('#(.*)\((\d+)\)\s+:\s+eval\(\)\'d\s+code(\s+on\s+line\s+\d+)?$#', $message, $match)) {
            $file = $match[1];
            $line = (int) $match[2];
        }
    }
}
