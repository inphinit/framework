<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Viewing;

use Inphinit\App;

class View
{
    private static $force = false;
    private static $views = array();
    private static $shared = array();

    /**
     * Force the `View::render` method to render at the time it is called
     *
     * @return void
     */
    public static function forceRender()
    {
        self::$force = true;
    }

    /**
     * Starts rendering of registered views. After calling this method call it will automatically
     * execute `View::forceRender()`
     *
     * @return void
     */
    public static function dispatch()
    {
        if (self::$force === false) {
            self::forceRender();

            foreach (self::$views as &$value) {
                if ($value) {
                    self::render($value[0], $value[1]);
                }
            }
        }
    }

    /**
     * Share or remove shared data to Views, shared variables will be added as variables to the views that will be executed later
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public static function data($key, $value)
    {
        if ($value === null) {
            unset(self::$shared[$key]);
        } else {
            self::$shared[$key] = $value;
        }
    }

    /**
     * Check if view exists in ./application/View/ folder
     *
     * @param string $view
     * @return bool
     */
    public static function exists($view)
    {
        return inphinit_path_check(INPHINIT_PATH . 'application/View/' . str_replace('.', '/', $view) . '.php');
    }

    /**
     * Register or render a View. If View is registered this method returns the index number from View
     *
     * @param string $view
     * @param array  $data
     * @return int
     */
    public static function render($view, array $data = array())
    {
        if (!self::$force) {
            return array_push(self::$views, array($view, $data)) - 1;
        }

        $data += self::$shared;

        inphinit_sandbox('application/View/' . str_replace('.', '/', $view) . '.php', $data);

        return -1;
    }

    /**
     * Remove a registered View by index
     *
     * @param int $index
     * @return void
     */
    public static function remove($index)
    {
        if (isset(self::$views[$index])) {
            unset(self::$views[$index]);
        }
    }
}
