<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Uri;
use Inphinit\Http\Request;
use Inphinit\Http\Response;
use Inphinit\Experimental\Exception;

class Redirect
{
    private static $debuglvl = 2;

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
        self::$debuglvl = 3;

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
        $debuglvl = self::$debuglvl;
        self::$debuglvl = 2;

        if (headers_sent()) {
            throw new Exception('Headers already sent', $debuglvl);
        } elseif ($code < 300 || $code > 399) {
            throw new Exception('Invalid redirect HTTP status', $debuglvl);
        } elseif (empty($path)) {
            throw new Exception('Path is not defined', $debuglvl);
        } elseif (strpos($path, '/') === 0) {
            $path = Uri::root($path);
        }

        Response::status($code, $trigger);
        Response::putHeader('Location', $path);
    }

    /**
     * Return to redirect to new path
     *
     * @param bool $only
     * @param bool $trigger
     * @return bool|void
     */
    public static function back($only = false, $trigger = true)
    {
        $referer = Request::header('referer');

        if ($referer === false) {
            return false;
        }

        self::$debuglvl = 3;

        if ($only) {
            static::only($referer, 302, $trigger);
        } else {
            static::to($referer, 302, $trigger);
        }
    }
}
