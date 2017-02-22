<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Regex
{
    private static $rearg = '[\[\]{}()?^$\#*+\\.]';

    /**
     * Parse string like.: `{:[a-z]+:}.domain.com` or `/user/{:[a-z]+:}/{:\d+:}` to regex
     *
     * @param string $str
     * @return bool|string
     */
    public static function parse($str)
    {
        if (preg_match('#\{:.*?:\}#', $str) === 0) {
            return false;
        }

        $str = preg_replace('#' . self::$rearg . '#', '\\\$0', $str);
        return preg_replace_callback('#\\\{:.*?:\\\}#',
                    array( '\\' . get_called_class(), 'args' ), $str);
    }

    /**
     * Convert one argument like `{:[a-z]+:}` to `([a-z]+)`,
     * this function is used by `Router::parse`
     *
     * @param string $arg
     * @return string
     */
    public static function args($arg)
    {
        $arg = preg_replace('#\\\\(' . self::$rearg . ')#', '$1', $arg[0]);
        return '(' . substr($arg, 2, -2) . ')';
    }
}