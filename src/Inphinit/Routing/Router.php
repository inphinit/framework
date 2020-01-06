<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
    protected static $prefixPath = '';

    /**
     * Get params from routes using regex
     *
     * @param string $route
     * @param string $path
     * @param array  $matches
     * @return bool
     */
    protected static function find($route, $path, array &$matches)
    {
        if (strpos($route, '{:') !== false) {
            $re = Regex::parse($route);

            if ($re !== false && preg_match('#^' . $re . '$#', $path, $matches)) {
                array_shift($matches);
                return true;
            }
        }

        return false;
    }
}
