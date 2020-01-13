<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
     * @param string|array         $method
     * @param string               $path
     * @param string|\Closure|null $action
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

            $path = parent::$prefixPath . $path;

            if (!isset(parent::$httpRoutes[$path])) {
                parent::$httpRoutes[$path] = array();
            }

            parent::$httpRoutes[$path][ strtoupper(trim($method)) ] = $action;
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
        $routes = &parent::$httpRoutes;
        $path = \UtilsPath();
        $method = $_SERVER['REQUEST_METHOD'];

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
