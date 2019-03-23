<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
     * @param string|array    $method
     * @param string          $path
     * @param string|\Closure $action
     * @return void
     */
    public static function set($method, $path, $action)
    {
        if (is_array($method)) {
            foreach ($method as $value) {
                self::set($value, $path, $action);
            }
        } else {
            if (is_string($action)) {
                $action = parent::$prefixNS . $action;
            } elseif ($action !== null && !$action instanceof \Closure) {
                return null;
            }

            $verb = strtoupper(trim($method)) . ' ' . parent::$prefixPath . $path;
            parent::$httpRoutes[$verb] = $action;
        }
    }

    /**
     * Get action controller from current route
     *
     * @return array|bool
     */
    public static function get()
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $verb = false;
        $resp = 404;

        $args = array();

        $routes = array_filter(parent::$httpRoutes);
        $pathinfo = \UtilsPath();
        $httpMethod = $_SERVER['REQUEST_METHOD'];

        $verb = 'ANY ' . $pathinfo;
        $http = $httpMethod . ' ' . $pathinfo;

        if (isset($routes[$verb])) {
            $resp = $routes[$verb];
        } elseif (isset($routes[$http])) {
            $resp = $routes[$http];
        } else {
            foreach ($routes as $route => $action) {
                if (parent::find($httpMethod, $route, $pathinfo, $args)) {
                    $resp = $action;
                    break;
                }

                if (substr($route, strpos($route, ' ') + 1) === $pathinfo) {
                    $resp = 405;
                    break;
                }
            }
        }

        if (is_numeric($resp)) {
            self::$current = $resp;
        } else {
            self::$current = array(
                'callback' => $resp, 'args' => $args
            );
        }

        $routes = null;

        return self::$current;
    }
}
