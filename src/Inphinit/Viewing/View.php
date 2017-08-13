<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
        self::forceRender();

        foreach (self::$views as $value) {
            $value && self::render($value[0], $value[1]);
        }

        self::$views = null;
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
        $path = INPHINIT_PATH . 'application/View/' . strtr($view, '.', '/') . '.php';
        return is_file($path) && \UtilsCaseSensitivePath($path);
    }

    /**
     * Register or render a View. If View is registered this method returns the index number from View
     *
     * @param string $view
     * @param array $data
     * @return int|null
     */
    public static function render($view, array $data = array())
    {
        if (self::$force || App::isReady()) {
            \UtilsSandboxLoader('application/View/' . strtr($view, '.', '/') . '.php',
                self::$shared + $data);

            return $data = null;
        }

        return array_push(self::$views, array(strtr($view, '.', '/'), $data)) - 1;
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
            self::$views[$index] = null;
        }
    }
}
