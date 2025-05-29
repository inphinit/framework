<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Event
{
    private static $events = array();
    private static $nonSorted = true;
    private static $uniques = array(
        'done' => false
    );

    /**
     * Trigger registered events
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

        if (self::$nonSorted) {
            self::$nonSorted = false;

            usort($listen, function ($a, $b) {
                if ($a[1] === $b[1]) {
                    return 0;
                }

                return $a[1] > $b[1] ? 1 : -1;
            });
        }

        foreach ($listen as $callback) {
            if (call_user_func_array($callback[0], $args) === false) {
                break;
            }
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
            self::$nonSorted = true;
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
