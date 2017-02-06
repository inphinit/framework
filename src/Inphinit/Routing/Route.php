<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
     * @param string|array $method
     * @param string       $path
     * @param string       $action
     * @return void
     */
    public static function set($method, $path, $action)
    {
        if (is_array($method)) {
            foreach ($method as $value) {
                self::set($value, $path, $action);
            }
        } elseif (ctype_alpha($method) && is_string($path) && ($action === null || is_string($action))) {
            $verb = strtoupper(trim($method)) . ' ' . parent::$prefixPath . $path;
            self::$httpRoutes[$verb] = $action === null ? $action : parent::$prefixNS . $action;
        }
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
    private static function find($httpMethod, $route, $pathinfo, &$matches)
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
                if (self::find($httpMethod, $route, $pathinfo, $args)) {
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
