<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Event
{
    /** @var int Priority level for events that should be executed before others */
    const HIGH_PRIORITY = 1;

    /** @var int Priority level for events that should be executed after higher-priority ones */
    const LOW_PRIORITY = -1;

    /** @var int Returned when no callbacks are registered for the specified event type */
    const TRIGGER_UNDEFINED = 0;

    /** @var int Returned when a one-time event is triggered more than once */
    const TRIGGER_CONSUMED = 1;

    /** @var int Returned when a callback within the event returns false, stopping further execution */
    const TRIGGER_STOPPED = 2;

    /** @var int Returned when all callbacks complete successfully without returning false */
    const TRIGGER_SUCCESS = 3;

    private static $events = array();
    private static $uniques = array('done' => false);
    private static $unordered = array();

    /**
     * Triggers all callbacks registered for a given event
     *
     * @param string $name Event name
     * @param array  $args Arguments to pass to the callbacks
     * @return int
     */
    public static function trigger($name, array $args = array())
    {
        if (empty(self::$events[$name])) {
            return self::TRIGGER_UNDEFINED;
        }

        if (isset(self::$uniques[$name])) {
            if (self::$uniques[$name]) {
                return self::TRIGGER_CONSUMED;
            }

            self::$uniques[$name] = true;
        }

        $listen = &self::$events[$name];

        if (self::$unordered[$name]) {
            self::$unordered[$name] = false;

            usort($listen, function ($a, $b) {
                if ($a[1] === $b[1]) {
                    return 0;
                }

                return $a[1] < $b[1] ? 1 : -1;
            });
        }

        foreach ($listen as $callback) {
            if (call_user_func_array($callback[0], $args) === false) {
                return self::TRIGGER_STOPPED;
            }
        }

        return self::TRIGGER_SUCCESS;
    }

    /**
     * Registers a callback to an event with optional priority. Note: If any subscribed callback
     * returns a boolean false, further execution of subsequent callbacks will be halted
     *
     * @param string   $name     Event name
     * @param callable $callback Callback to execute when the event is triggered
     * @param int      $priority Execution priority (higher numbers run earlier). Default is 0
     */
    public static function on($name, callable $callback, $priority = 0)
    {
        if (is_string($name)) {
            if (!isset(self::$events[$name])) {
                self::$events[$name] = array();
            }

            self::$events[$name][] = array($callback, $priority);
            self::$unordered[$name] = true;
        }
    }

    /**
     * It makes a type of event unique, ensuring that
     * all registered events of that type are triggered only once.
     *
     * @param string $name Event name
     */
    public static function once($name)
    {
        self::$uniques[$name] = false;
    }

    /**
     * Removes a specific callback or all callbacks registered for an event.
     * If $callback is null, all listeners for the given event are removed.
     *
     * @param string        $name     Event name
     * @param callable|null $callback Specific callback to remove, or null to remove all
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
     * Clears all registered events and uniqueness flags
     */
    public static function clear()
    {
        self::$events = array();
        self::$uniques = array('done' => false);
        self::$unordered = array();
    }
}
