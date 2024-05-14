<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
        } elseif ($action !== null && !is_callable($action)) {
            return null;
        }

        if (strpos($path, '{:') !==false) {
            self::$hasParams = true;
        }

        $path = parent::$prefixPath . $path;

        $routes = &parent::$httpRoutes;

        if (!isset($routes[$path])) {
            $routes[$path] = array();
        }

        if (is_array($method)) {
            foreach ($method as $value) {
                $routes[$path][strtoupper($value)] = $action;
            }
        } else {
            $routes[$path][strtoupper($method)] = $action;
        }
    }

    /**
     * Get action controller from current route
     *
     * @return array|int
     */
    public static function get()
    {
        if (self::$current === null) {
            $args = array();
            $routes = &parent::$httpRoutes;
            $path = INPHINIT_PATHINFO;
            $method = $_SERVER['REQUEST_METHOD'];

            if (isset($routes[$path])) {
                $verbs = $routes[$path];
            } elseif (self::$hasParams) {
                foreach ($routes as $route => $actions) {
                    if (parent::find($route, $path, $args)) {
                        $verbs = $actions;
                        break;
                    }
                }
            }

            $resp = null;

            if (isset($verbs[$method])) {
                $resp = $verbs[$method];
            } elseif (isset($verbs['ANY'])) {
                $resp = $verbs['ANY'];
            } elseif (isset($verbs) && array_filter($verbs)) {
                $resp = 405;
            } else {
                $resp = 404;
            }

            if (is_int($resp)) {
                self::$current = $resp;
            } else {
                self::$current = array(
                    'callback' => $resp,
                    'args' => $args
                );
            }
        }

        return self::$current;
    }
}
