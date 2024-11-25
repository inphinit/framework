<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\App;
use Inphinit\Exception;
use Inphinit\Http\Request;
use Inphinit\Http\Response;
use Inphinit\Uri;

class Redirect
{
    private static $debuglvl = 2;

    /**
     * Redirect and stop application execution
     *
     * @param string $path
     * @param int    $code
     * @param bool   $trigger
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function only($path, $code = 302, $trigger = true)
    {
        self::$debuglvl = 3;

        self::to($path, $code, $trigger);

        App::trigger('finish');

        exit;
    }

    /**
     * Redirects to a valid path within the application
     *
     * @param string $path
     * @param int    $code
     * @param bool   $trigger
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function to($path, $code = 302, $trigger = true)
    {
        $debuglvl = self::$debuglvl;

        self::$debuglvl = 2;

        if (headers_sent($filename, $line)) {
            throw new Exception("HTTP headers already sent by $filename:$line", 0, $debuglvl);
        } elseif ($code < 300 || $code > 399) {
            throw new Exception('Invalid redirect HTTP status', 0, $debuglvl);
        } elseif (isset($path[0]) === false) {
            throw new Exception('Path is not defined', 0, $debuglvl);
        } elseif (strpos($path, '/') === 0) {
            $path = dirname($_SERVER['SCRIPT_NAME']);

            if ($path === '\\' || $path === '/') {
                $path = '';
            }

            $path .= '/';
        }

        Response::status($code, $trigger);
        header('Location: ' . $path);
    }

    /**
     * Return to redirect to new path
     *
     * @param bool $only
     * @param bool $trigger
     * @return bool
     */
    public static function back($only = false, $trigger = true)
    {
        $referer = Request::header('referer');

        if ($referer === null) {
            return false;
        }

        self::$debuglvl = 3;

        if ($only) {
            static::only($referer, 302, $trigger);
        } else {
            static::to($referer, 302, $trigger);
        }

        return true;
    }
}
