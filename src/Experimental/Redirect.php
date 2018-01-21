<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Uri;
use Inphinit\Regex;
use Inphinit\Http\Request;
use Inphinit\Http\Response;

class Redirect extends \Inphinit\Routing\Router
{
    /**
     * Redirect and stop application execution
     *
     * @param string $path
     * @param int    $code
     * @param bool   $trigger
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public static function only($path, $code = 302, $trigger = true)
    {
        self::to($path, $code, $trigger);

        Response::dispatch();

        exit;
    }

    /**
     * Redirects to a valid path within the application
     *
     * @param string $path
     * @param int    $code
     * @param bool   $trigger
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public static function to($path, $code = 302, $trigger = true)
    {
        if (headers_sent()) {
            throw new Exception('Headers already sent', 2);
        } elseif ($code < 300 || $code > 399) {
            throw new Exception('Invalid redirect HTTP status', 2);
        } elseif (empty($path)) {
            throw new Exception('Path is not defined', 2);
        } elseif (strpos($path, '/') === 0) {
            $path = Uri::root($path);
        }

        Response::status($code);
        Response::putHeader('Location', $path);
    }

    /**
     * Return to redirect to new path
     *
     * @param bool $trigger
     * @return bool|void
     */
    public static function back($only = false, $trigger = true)
    {
        $referer = Request::header('referer');

        if ($referer === false) {
            return false;
        } elseif ($only) {
            static::only($referer, 302, $trigger);
        } else {
            static::to($referer, 302, $trigger);
        }
    }

    /**
     * Redirect to route based
     *
     * @return void
     */
    public static function action($name, array $args = array(), $code = 302)
    {
        $verb = array_search($name, parent::$httpRoutes);

        if ($verb === false) {
            throw new Exception('Action not defined in route', 2);
        } elseif (strpos($verb, 'GET /') !== 0 && strpos($verb, 'ANY /') !== 0) {
            throw new Exception('Method not allowed', 2);
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
                throw new Exception('Invalid URL from regex: ' . $verb, 2);
            }
        } elseif ($ac) {
            throw new Exception('Invalid number of arguments', 2);
        }

        self::to($url, $code);
    }
}
