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
     * @var string
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

    public static function args($rearg)
    {
        $rearg = str_replace('\\', '', $rearg[0]);
        $rearg = ltrim($rearg, '{');
        $rearg = rtrim($rearg, '}');
        return '(' . $rearg . ')';
    }
}
