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
    const HIGH_PRIORITY = 1;
    const LOW_PRIORITY = -1;

    private static $events = array();
    private static $nonSorted = true;
    private static $uniques = array(
        'done' => false
    );

    /**
     * Trigger an event
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

                return $a[1] < $b[1] ? 1 : -1;
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
     * Subscribe an action to an event. Events with higher priority will be executed first.
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
     * Makes an event unique
     *
     * @param string $name
     * @return void
     */
    public static function once($name)
    {
        self::$uniques[$name] = false;
    }

    /**
     * Unsubscribe 1 or all events
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

    /**
     * Clear all events
     *
     * @return void
     */
    public static function clear()
    {
        self::$events = array();
        self::$uniques = array();
        self::$nonSorted = true;
    }
}
