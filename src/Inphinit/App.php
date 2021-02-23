<?php
/*
 * Inphinit
 *
 * Copyright (c) 2021 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
    const VERSION = '0.5.15';

    private static $events = array();
    private static $configs = array();
    private static $state = 0;

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
        if (empty(self::$events[$name]) === false) {
            if ($callback === null) {
                self::$events[$name] = array();
                return null;
            }

            $evts = &self::$events[$name];

            foreach ($evts as $key => $value) {
                if ($value[0] === $callback) {
                    unset($evts[$key]);
                }
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

        if (self::env('maintenance')) {
            $resp = 503;
        } else {
            $resp = \UtilsStatusCode();
        }

        //200 is initial value in commons webservers
        if ($resp === 200) {
            $resp = Route::get();
        }

        if (is_integer($resp)) {
            self::on('finish', function () use ($resp) {
                \UtilsSandboxLoader('error.php', array( 'status' => $resp ));
            });
            
            self::stop($resp);
        }

        $callback = &$resp['callback'];

        if (is_string($callback)) {
            $parsed = explode(':', $callback, 2);

            $callback = '\\Controller\\' . strtr($parsed[0], '.', '\\');
            $callback = array(new $callback, $parsed[1]);
        }

        $output = call_user_func_array($callback, $resp['args']);

        self::dispatch();

        if (self::$state < 2) {
            self::$state = 2;
        }

        self::trigger('ready');

        if ($output !== false && $output !== null) {
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
    private static function dispatch()
    {
        if (class_exists('\\Inphinit\\Http\\Response', false)) {
            Response::dispatch();
        }

        if (class_exists('\\Inphinit\\Viewing\\View', false)) {
            View::dispatch();
        }
    }
}
