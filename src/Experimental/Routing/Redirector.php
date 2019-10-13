<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Routing;

use Inphinit\Regex;
use Inphinit\Experimental\Exception;
use Inphinit\Experimental\Http\Redirect;

class Redirector extends \Inphinit\Routing\Router
{
    /**
     * Redirect to route based
     *
     * @param string $route
     * @param array  $args
     * @param int    $code
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public static function route($route, array $args = array(), $code = 302)
    {
        $verbs = array_keys(parent::$httpRoutes);
        $route = '/' . ltrim($route, '/');
        $to = false;

        foreach ($verbs as $verb) {
            if (preg_match('#^(GET|ANY) (/|/[\s\S]+)$#', $verb, $out) && $out[2] === $route) {
                $to = $verb;
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
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public static function action($name, array $args = array(), $code = 302)
    {
        $verb = array_search($name, parent::$httpRoutes);

        if ($verb === false) {
            throw new Exception('Controller or method is not defined', 2);
        } elseif (strpos($verb, 'GET /') !== 0 && strpos($verb, 'ANY /') !== 0) {
            throw new Exception('Method not allowed', 2);
        }

        self::redirect($verb, $args, $code);
    }

    private static function redirect($verb, $args, $code)
    {
        if ($verb === false) {
            throw new Exception('Route or Action not defined in route', 3);
        }

        $url = substr($verb, 4);
        $j = preg_match_all('#\{:.*?:\}#', $url);
        $i = count($args);
        $ac = $j > 0 || $i > 0;

        if ($ac && $j === $i) {
            $i = 0;

            $to = preg_replace_callback('#\{:.*?:\}#', function () use ($args, &$i) {
                return $args[$i++];
            }, $url);

            if (!preg_match('#' . Regex::parse($url) . '#', $to)) {
                throw new Exception('Invalid URL from regex: ' . $verb, 3);
            }
        } elseif ($ac) {
            throw new Exception('Invalid number of arguments', 3);
        }

        Redirect::to($url, $code);
    }
}
