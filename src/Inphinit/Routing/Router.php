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
     * Store all routes
     *
     * @var array
     */
    protected static $httpRoutes = array();

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

        $str = preg_replace('#[\[\]{}()\-+~=.\^$\#\\\\]#', '\\\$0', $str);
        return preg_replace_callback('#\\\{:.*?:\\\}#',
                    array( '\\' . get_called_class(), 'args' ), $str);
    }

    /**
     * Convert one argument like `{:[a-z]+:}` to `([a-z]+)`,
     * this function is used by `Router::parse`
     *
     * @param string $rearg
     * @return string
     */
    public static function args($rearg)
    {
        $rearg = preg_replace('#\\\\([\[\]{}()\-+~=.\^$\#\\\\])#', '$1', $rearg[0]);
        return '(' . substr($rearg, 2, -2) . ')';
    }

    /**
     * Get params from routes using regex
     *
     * @param string $httpMethod
     * @param string $route
     * @param string $pathinfo
     * @param array  $matches
     * @return bool
     */
    protected static function find($httpMethod, $route, $pathinfo, &$matches)
    {
        $match = explode(' ', $route, 2);

        if ($match[0] !== 'ANY' && $match[0] !== $httpMethod) {
            return false;
        }

        $re = self::parse($match[1]);

        if ($re !== false && preg_match('#^' . $re . '$#', $pathinfo, $matches) > 0) {
            array_shift($matches);
            return true;
        }

        return false;
    }
}
