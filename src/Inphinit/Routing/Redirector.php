<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

use Inphinit\Regex;
use Inphinit\Exception;
use Inphinit\Http\Redirect;

class Redirector extends Router
{
    /**
     * Redirect to route based
     *
     * @param string $route
     * @param array  $args
     * @param int    $code
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function route($route, array $args = array(), $code = 302)
    {
        $verbs = array_keys(parent::$httpRoutes);
        $route = '/' . ltrim($route, '/');
        $to = false;

        $route = self::$prefixPath . $route;

        foreach (parent::$httpRoutes as $path => &$croute) {
            $method = key($croute);

            if (($method === 'GET' || $method === 'ANY') && isset($croute[$method])) {
                $to = $path;
                break;
            }
        }

        self::redirect($to, $args, $code);
    }

    /**
     * Redirect to route based
     *
     * @param string $name
     * @param array  $args
     * @param int    $code
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function action($name, array $args = array(), $code = 302)
    {
        $to = false;

        foreach (parent::$httpRoutes as $path => &$croute) {
            $method = key($croute);

            if (($method === 'GET' || $method === 'ANY') && $croute[$method] === $name) {
                $to = $path;
                break;
            }
        }

        self::redirect($to, $args, $code);
    }

    private static function redirect($path, $args, $code)
    {
        if ($path === false) {
            throw new Exception('Route or Action not defined in route', 0, 3);
        }

        $j = preg_match_all('#\{:.*?:\}#', $path);

        $i = count($args);
        $ac = $j > 0 || $i > 0;

        if ($ac && $j === $i) {
            $i = -1;

            $to = preg_replace_callback('#\{:.*?:\}#', function () use ($args, &$i) {
                return $args[++$i];
            }, $path);

            if (!preg_match('#' . Regex::parse($path) . '#', $to)) {
                throw new Exception('Invalid URL from regex: ' . $verb, 0, 3);
            }
        } elseif ($ac) {
            throw new Exception('Invalid number of arguments', 0, 3);
        }

        Redirect::to($to, $code);
    }
}
