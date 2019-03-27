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

            $method = strtoupper(trim($method));

            $path = parent::$prefixPath . $path;

            if (!isset(parent::$httpRoutes[$path])) {
                parent::$httpRoutes[$path] = array();
            }

            parent::$httpRoutes[$path][$method] = $action;
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

        $resp = 404;

        $args = array();

        $routes = parent::$httpRoutes;
        $path = \UtilsPath();
        $method = $_SERVER['REQUEST_METHOD'];

        //...
        if (isset($routes[$path])) {
            $verbs = $routes[$path];
        } else {
            foreach ($routes as $route => $actions) {
                if (parent::find($route, $path, $args)) {
                    $verbs = $actions;
                    break;
                }
            }
        }

        if (isset($verbs[$method])) {
            $resp = $verbs[$method];
        } elseif (isset($verbs['ANY'])) {
            $resp = $verbs['ANY'];
        } elseif (isset($verbs)) {
            $resp = 405;
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
