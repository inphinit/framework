<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

abstract class Router
{
    /**
     * Define namespace prefix to Controllers
     *
     * @var string
     */
    protected static $prefixNS = '';

    /**
     * Define path prefix to routes
     *
     * @var string
     */
    protected static $prefixPath = '';

    /**
     * Parse string like.: {[a-z]+}.domain.com or /user/{[a-z]+}/{\d+} to regex
     *
     * @param string $str
     * @return bool|string
     */
    public static function parse($str)
    {
        if (preg_match('#\{(.*?)\}#', $str) === 0) {
            return false;
        }

        $str = preg_replace('#[\[\]\{\}\(\)\-\+\~\=\^\$\.]#', '\\\$0', $str);

        return preg_replace_callback('#\\\{.*?\\\}#',
                    array( '\\' . get_called_class(), 'args' ), $str);
    }

    /**
     * Convert one argument like {[a-z]+} to ([a-z]+), this function is used by `Router::parse`
     *
     * @param string $rearg
     * @return string
     */
    public static function args($rearg)
    {
        $rearg = str_replace('\\', '', $rearg[0]);
        return '(' . substr($rearg, 1, -1) . ')';
    }
}
