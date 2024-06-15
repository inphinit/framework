<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Event
{
    private static $events = array();
    private static $uniques = array(
        'done' => false
    );

    /**
     * Trigger registered event
     *
     * @param string $name
     * @param array  $args
     * @return bool
     */
    public static function trigger($name, array $args = array())
    {
        if (empty(self::$events[$name])) {
            return false;
        }

        if (isset(self::$uniques[$name])) {
            if (self::$uniques[$name]) {
                return false;
            }

            self::$uniques[$name] = true;
        }

        $listen = &self::$events[$name];

        usort($listen, function ($a, $b) {
            if ($a[1] === $b[1]) {
                return 0;
            }

            return $a[1] > $b[1] ? 1 : -1;
        });

        foreach ($listen as $callback) {
            call_user_func_array($callback[0], $args);
        }

        return true;
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
    public static function off($name, $callback = null)
    {
        if ($callback === null) {
            self::$events[$name] = array();
        } elseif (isset(self::$events[$name])) {
            $evts = &self::$events[$name];

            foreach ($evts as $key => $value) {
                if ($value[0] === $callback) {
                    unset($evts[$key]);
                }
            }
        }
    }
}
