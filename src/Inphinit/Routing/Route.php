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
        } elseif (ctype_alpha($method) && is_string($path)) {
            if (is_string($action)) {
                $action = parent::$prefixNS . $action;
            } else if ($action !== null && ($action instanceof \Closure) === false) {
                return null;
            }

            $verb = strtoupper(trim($method)) . ' ' . parent::$prefixPath . $path;
            parent::$httpRoutes[$verb] = $action;
        }
    }

    /**
     * Get action controller from current route
     *
     * @return string|bool
     */
    public static function get()
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $func = false;
        $verb = false;

        $args = null;

        $routes = array_filter(parent::$httpRoutes);
        $pathinfo = \UtilsPath();
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        $verb = 'ANY ' . $pathinfo;
        $http = $httpMethod . ' ' . $pathinfo;

        if (isset($routes[$verb])) {
            $func = $routes[$verb];
        } elseif (isset($routes[$http])) {
            $func = $routes[$http];
        } elseif (empty($routes) === false) {
            foreach ($routes as $route => $action) {
                if (parent::find($httpMethod, $route, $pathinfo, $args)) {
                    $func = $action;
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

        $routes = null;

        return self::$current;
    }
}
