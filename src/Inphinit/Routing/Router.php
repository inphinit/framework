<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

use Inphinit\Regex;

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
    public static $prefixPath = '';

    /**
     * Get params from routes using regex
     *
     * @param string $httpMethod
     * @param string $route
     * @param string $pathinfo
     * @param array  $matches
     * @return bool
     */
    protected static function find($httpMethod, $route, $pathinfo, array &$matches)
    {
        $match = explode(' ', $route, 2);

        if ($match[0] !== 'ANY' && $match[0] !== $httpMethod) {
            return false;
        }

        $re = Regex::parse($match[1]);

        if ($re !== false && preg_match('#^' . $re . '$#', $pathinfo, $matches) > 0) {
            array_shift($matches);
            return true;
        }

        return false;
    }
}
