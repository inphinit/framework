<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class View
{
    private static $views = array();
    private static $sharedData = array();

    private static $force = false;

    public static function forceRender()
    {
        self::$force = true;
    }

    public static function dispatch()
    {
        $views = self::$views;

        self::forceRender();

        if (empty($views) === false) {
            foreach ($views as $value) {
                self::render($value[0], $value[1]);
            }

            self::$views = $views = null;
        }
    }

    public static function shareData($key, $value)
    {
        self::$sharedData[$key] = $value;
    }

    public static function removeData($key = null)
    {
        if ($key === null) {
            self::$data = array();
        } else {
            self::$data[$key] = null;
            unset(self::$data[$key]);
        }
    }

    public static function exists($view)
    {
        $path = INPHINIT_PATH . 'application/View/' . strtr($view, '.', '/') . '.php';
        return is_file($path) && \UtilsCaseSensitivePath($path);
    }

    public static function render($view, array $data = array())
    {
        if (self::$force) {
            $data = self::$sharedData + $data;

            \UtilsSandboxLoader('application/View/' . strtr($view, '.', '/') . '.php', $data);

            $data = null;

            return null;
        }

        self::$views[] = array(strtr($view, '.', '/'), $data);
        return count(self::$views);
    }

    public static function remove($id)
    {
        if (isset(self::$view[$id])) {
            self::$view[$id] = null;
        }
    }
}
