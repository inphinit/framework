<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

class Route extends Router
{
    private static $httpRoutes = array();
    private static $current;

    /**
     * Register or remove a action from controller for a route
     *
     * @param  string|array  $method
     * @param  mixed         $path
     * @param  mixed         $action
     * @return void
     */
    public static function set($method, $path, $action)
    {
        if (is_array($method)) {
            foreach ($method as $value) {
                self::set($value, $path, $action);
            }
        } elseif (ctype_alpha($method) && is_string($path) && ($action !== null || is_string($action))) {
            $verb = strtoupper(trim($method)) . ' ' . parent::$prefixPath . $path;
            self::$httpRoutes[$verb] = parent::$prefixNS . $action;
        }
    }

    /**
     * Get params from routes using regex
     *
     * @param  string         $httpMethod
     * @param  string         $route
     * @param  string         $pathinfo
     * @param  array          $matches
     * @return boolean
     */
    private static function find($httpMethod, $route, $pathinfo, &$matches)
    {
        $match = explode(' re:', $route, 2);

        if ($match[0] !== 'ANY' && $match[0] !== $httpMethod) {
            return false;
        }

        if (preg_match($match[1], $pathinfo, $matches) > 0) {
            array_shift($matches);
            return true;
        }

        return false;
    }

    /**
     * Get action controller from current route
     *
     * @return string
     */
    public static function get()
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $func = false;
        $verb = false;

        $args = null;

        $routes = array_filter(self::$httpRoutes);
        $pathinfo = \UtilsPath();
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        $verb = 'ANY ' . $pathinfo;
        $http = $httpMethod . ' ' . $pathinfo;

        if (isset($routes[$verb])) {
            $func = $routes[$verb];
        } elseif (isset($routes[$http])) {
            $func = $routes[$http];
            $verb = $http;
        } elseif (empty($routes) === false) {
            foreach ($routes as $route => $action) {
                if (strpos($route, ' re:') !== false && self::find($httpMethod, $route, $pathinfo, $args)) {
                    $func = $action;
                    $verb = $route;
                    break;
                }
            }
        }

        if ($func !== false) {
            self::$current = array(
                'controller' => $func, 'args' => $args
            );
        } else {
            self::$current = false;
        }

        $routes = self::$httpRoutes = null;

        return self::$current;
    }
}
