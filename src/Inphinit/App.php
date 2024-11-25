<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Http\Response;
use Inphinit\Routing\Route;
use Inphinit\Viewing\View;

class App
{
    /** Inphinit framework version */
    const VERSION = '1.0.1';

    private static $configs;
    private static $events = array();
    private static $state = 0;

    /**
     * Get application configs
     *
     * @param string $key
     * @return mixed
     */
    public static function config($key, $value = null)
    {
        if (self::$configs === null) {
            self::$configs = inphinit_sandbox('application/Config/config.php');
        }

        if (array_key_exists($key, self::$configs)) {
            if ($value == null) {
                return self::$configs[$key];
            }

            self::$configs[$key] = $value;
        }
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
        if ($name === 'error') {
            self::$state = 5;
        }

        if (isset(self::$events[$name])) {
            $listen = &self::$events[$name];

            usort($listen, function ($a, $b) {
                return $b[1] >= $a[1];
            });

            foreach ($listen as $callback) {
                call_user_func_array($callback[0], $args);
            }
        }
    }

    /**
     * Return application state
     *
     * <ul>
     * <li>0 - Unexecuted - `App::exec()` is not executed</li>
     * <li>1 - Initiated - `App::exec()` is executed</li>
     * <li>2 - Interactive - After dispatch headers and views</li>
     * <li>3 - Ready - After show return in output</li>
     * <li>4 - Finished - Defined after trigger finish event</li>
     * <li>5 - Error - Defined after trigger an error event (by user or by script)</li>
     * </ul>
     *
     * @return int
     */
    public static function state()
    {
        return self::$state;
    }

    /**
     * Register an event
     *
     * @param string   $name
     * @param callable $callback
     * @param int      $priority
     * @return void
     */
    public static function on($name, callable $callback, $priority = 0)
    {
        if (is_string($name)) {
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
    public static function off($name, callable $callback = null)
    {
        if (empty(self::$events[$name]) === false) {
            if ($callback === null) {
                $evts = &self::$events[$name];

                foreach ($evts as $key => $value) {
                    if ($value[0] === $callback) {
                        unset($evts[$key]);
                    }
                }
            } else {
                self::$events[$name] = array();
            }
        }
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
        if (Response::status($code, false)) {
            self::trigger('changestatus', array($code, $msg));
        }

        self::trigger('finish');

        if (self::$state < 4) {
            self::$state = 4;
        }

        self::dispatch();

        exit;
    }

    /**
     * Start application using routes
     *
     * @return void
     */
    public static function exec()
    {
        if (self::$state > 0) {
            return null;
        }

        self::trigger('init');

        self::$state = 1;

        $resp = self::config('maintenance') ? 503 : http_response_code();

        //200 is initial value in commons webservers
        if ($resp === 200) {
            $resp = Route::get();
        }

        if (is_integer($resp)) {
            $data = array('status' => $resp);
            inphinit_sandbox('error.php', $data);
            self::stop($resp);
        }

        $callback = &$resp['callback'];

        if (is_string($callback) && strpos($callback, ':') !== false) {
            $parsed = explode(':', $callback, 2);

            $callback = '\\Controller\\' . str_replace('.', '\\', $parsed[0]);
            $callback = array(new $callback, $parsed[1]);
        }

        $output = call_user_func_array($callback, $resp['args']);

        self::dispatch();

        if (self::$state < 2) {
            self::$state = 2;
        }

        self::trigger('ready');

        if ($output !== null) {
            echo $output;
        }

        if (self::$state < 3) {
            self::$state = 3;
        }
        
        self::trigger('finish');

        if (self::$state < 4) {
            self::$state = 4;
        }
    }

    /**
     * Dispatch before ready event if exec is Ok,
     * or dispatch after finish event if stop() is executed
     */
    public static function dispatch()
    {
        if (class_exists('\\Inphinit\\Viewing\\View', false)) {
            View::dispatch();
        }
    }
}
