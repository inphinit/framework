<?php
/*
 * Inphinit
 *
 * Copyright (c) 2021 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

class Route extends Router
{
    private static $current;
    private static $hasParams = false;

    /**
     * Register or remove a action from controller for a route
     *
     * @param string|array         $method
     * @param string               $path
     * @param string|\Closure|null $action
     * @return void
     */
    public static function set($method, $path, $action)
    {
        if (is_string($action)) {
            $action = parent::$prefixNS . $action;
        } elseif ($action !== null && !$action instanceof \Closure) {
            return null;
        }

        if (strpos($path, '{:') !==false) {
            self::$hasParams = true;

            $routes = &parent::$httpParamRoutes;
        } else {
            $routes = &parent::$httpRoutes;
        }

        $path = parent::$prefixPath . $path;

        if (!isset($routes[$path])) {
            $routes[$path] = array();
        }

        $routes[$path][ strtoupper(trim($method)) ] = $action;

        if (is_array($method)) {
            foreach ($method as $value) {
                $routes[$path][ strtoupper(trim($value)) ] = $action;
            }
        } else {
            $routes[$path][ strtoupper(trim($method)) ] = $action;
        }
    }

    /**
     * Get action controller from current route
     *
     * @return array|int
     */
    public static function get()
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $args = array();
        $path = \UtilsPath();
        $method = $_SERVER['REQUEST_METHOD'];

        if (isset(parent::$httpRoutes[$path])) {
            $verbs = parent::$httpRoutes[$path];
        } elseif (self::$hasParams) {
            foreach (parent::$httpParamRoutes as $route => $actions) {
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
        } elseif (isset($verbs) && array_filter($verbs)) {
            return self::$current = 405;
        } else {
            return self::$current = 404;
        }

        return self::$current = array(
            'callback' => $resp, 'args' => $args
        );
    }
}
