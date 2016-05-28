<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Routing\Route;

class App
{
    private static $events = array();
    private static $configs = array();
    private static $initiate = false;
    private static $detectError = false;

    public static function env($key, $value = null)
    {
        if (is_string($value) || is_bool($value) || is_numeric($value)) {
            self::$configs[$key] = $value;
        } elseif ($value === null && isset(self::$configs[$key])) {
            return self::$configs[$key];
        }
    }

    public static function config($path)
    {
        $data = \UtilsSandboxLoader('application/Config/' . strtr($path, '.', '/') . '.php');

        if (empty($data) === false && is_array($data)) {
            foreach ($data as $key => $value) {
                self::env($key, $value);
            }
        }

        $data = null;
    }

    public static function trigger($event, array $args = array())
    {
        if (empty(self::$events[$event])) {
            return false;
        }

        $listen = self::$events[$event];

        if ($event === 'error') {
            self::$detectError = true;
        }

        foreach ($listen as $callback) {
            call_user_func_array($callback, $args);
        }

        $listen = null;
    }

    public static function hasError()
    {
        return self::$detectError;
    }

    public static function on($name, $callback)
    {
        if (is_string($name) === false || is_callable($callback) === false) {
            return false;
        }

        if (isset(self::$events[$name]) === false) {
            self::$events[$name] = array();
        }

        self::$events[$name][] = $callback;
    }

    public static function off($name, $callback = null)
    {
        if (empty(self::$events[$name])) {
            return false;
        } elseif ($callback === null) {
            self::$events[$name] = array();
            return null;
        }

        $evts = self::$events[$name];

        foreach ($evts as $key => $value) {
            if ($value === $callback) {
                unset($evts[$key]);
            }
        }

        self::$events[$name] = $evts;
        $evts = null;
    }

    public static function buffer($callback = null, $chunksize = 0, $flags = PHP_OUTPUT_HANDLER_STDFLAGS)
    {
        if (ob_get_level() !== 0) {
            ob_end_clean();
        }

        ob_start($callback, $chunksize, $flags);
    }

    public static function stop($code, $msg = null)
    {
        Response::status($code, true);

        self::trigger('changestatus', array($code, $msg));
        self::trigger('finish');

        exit;
    }

    public static function exec()
    {
        if (self::$initiate) {
            return null;
        }

        self::trigger('init');

        self::$initiate = true;

        if (self::env('maintenance') === true) {
            self::stop(503);
        }

        self::trigger('changestatus', array(\UtilsStatusCode(), null));

        $route = Route::get();

        if ($route) {
            $mainController = $route['controller'];
            $parsed = explode(':', $mainController, 2);

            $mainController = '\\Controller\\' . strtr($parsed[0], '.', '\\');
            $action = $parsed[1];

            $run = new $mainController;

            call_user_func_array(array($run, $action), is_array($route['args']) ? $route['args'] : array());

            $run = null;
        } else {
            App::stop(404, 'Invalid route');
        }

        if (class_exists('\\Inphinit\\Response', false)) {
            Response::dispatchHeaders();
        }

        if (class_exists('\\Inphinit\\View', false)) {
            View::dispatch();
        }

        self::trigger('ready');
        self::trigger('finish');
    }
}
