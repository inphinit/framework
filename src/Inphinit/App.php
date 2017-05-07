<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Routing\Route;

class App
{
    /**
     * Inphinit framework version
     *
     * @var string
     */
    const VERSION = '0.0.1';

    private static $events = array();
    private static $configs = array();
    private static $initiate = false;
    private static $error = false;
    private static $done = false;

    /**
     * Set or get environment value
     *
     * @param string                $key
     * @param string|bool|int|float $value
     * @return string|bool|int|float|void
     */
    public static function env($key, $value = null)
    {
        if (is_string($value) || is_bool($value) || is_numeric($value)) {
            self::$configs[$key] = $value;
        } elseif ($value === null && isset(self::$configs[$key])) {
            return self::$configs[$key];
        }
    }

    /**
     * Set environment variables by config files
     *
     * @param string $path
     * @return void
     */
    public static function config($path)
    {
        $data = \UtilsSandboxLoader('application/Config/' . strtr($path, '.', '/') . '.php');

        foreach ($data as $key => $value) {
            self::env($key, $value);
        }

        $data = null;
    }

    /**
     * Trigger registered event
     *
     * @param string $name
     * @param array  $args
     * @return void
     */
    public static function trigger($name, array $args = array())
    {
        if (empty(self::$events[$name])) {
            return null;
        }

        $listen = self::$events[$name];

        usort($listen, function ($a, $b) {
            return $b[1] >= $a[1];
        });

        if ($name === 'error') {
            self::$error = true;
        }

        foreach ($listen as $callback) {
            call_user_func_array($callback[0], $args);
        }

        $listen = null;
    }

    /**
     * Return `true` if `App::exec` is performed
     *
     * @return bool
     */
    public static function isReady()
    {
        return self::$done;
    }

    /**
     * Return true if a script or event trigged a error or exception
     *
     * @return bool
     */
    public static function hasError()
    {
        return self::$error;
    }

    /**
     * Register an event
     *
     * @param string   $name
     * @param callable $callback
     * @param int      $priority
     * @return void
     */
    public static function on($name, $callback, $priority = 0)
    {
        if (is_string($name) && is_callable($callback)) {
            if (isset(self::$events[$name]) === false) {
                self::$events[$name] = array();
            }

            self::$events[$name][] = array($callback, $priority);
        }
    }

    /**
     * Unregister 1 or all events
     *
     * @param string   $name
     * @param callable $callback
     * @return void
     */
    public static function off($name, $callback = null)
    {
        if (empty(self::$events[$name])) {
            return null;
        } elseif ($callback === null) {
            self::$events[$name] = array();
            return null;
        }

        $evts = self::$events[$name];

        foreach ($evts as $key => $value) {
            if ($value[0] === $callback) {
                unset($evts[$key]);
            }
        }

        self::$events[$name] = $evts;
        $evts = null;
    }

    /**
     * Stop application, send HTTP status
     *
     * @param int    $code
     * @param string $msg
     * @return void
     */
    public static function stop($code, $msg = null)
    {
        Response::status($code, true) && self::trigger('changestatus', array($code, $msg));
        self::trigger('finish');

        exit;
    }

    /**
     * Start application using routes
     *
     * @return void
     */
    public static function exec()
    {
        if (self::$initiate) {
            return null;
        }

        self::$initiate = true;

        self::trigger('init');

        if (self::env('maintenance')) {
            self::stop(503);
        }

        self::trigger('changestatus', array(\UtilsStatusCode(), null));

        $route = Route::get();

        if ($route === false) {
            self::stop(404, 'Invalid route');
        }

        $callback = $route['callback'];

        if (!$callback instanceof \Closure) {
            $parsed = explode(':', $callback, 2);

            $callback = '\\Controller\\' . strtr($parsed[0], '.', '\\');
            $callback = array(new $callback, $parsed[1]);
        }

        $output = call_user_func_array($callback, $route['args']);

        if (class_exists('\\Inphinit\\Response', false)) {
            Response::dispatch();
        }

        if (class_exists('\\Inphinit\\View', false)) {
            View::dispatch();
        }

        self::trigger('ready');

        self::$done = true;

        if ($output) {
            echo $output;
        }

        self::trigger('finish');
    }
}
