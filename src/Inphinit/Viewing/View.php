<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Viewing;

use Inphinit\Diagnostics\Inspector;
use Inphinit\Exception;

class View
{
    /** @var int Bypass security trust HTML in the render() method */
    const UNSAFE = -1;

    private static $encoding = 'UTF-8';
    private static $force = false;
    private static $shared = array();
    private static $strictMode = false;
    private static $views = array();

    /**
     * Set encoding used by escape engine
     */
    public static function setEncoding($value)
    {
        self::$encoding = $value;
    }

    /**
     * Enables or disables strict mode to pre-check for the existence of the view.
     *
     * - Note: When enabled, it performs a case-sensitive check on systems that do not support it
     * - Nota: In development environment, the framework will enable this by default
     * - Note: In production environment, it is recommended to disable it for performance reasons
     *
     * @param bool $enable
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function strict($enable)
    {
        if (is_bool($enable) === false) {
            $type = Inspector::type($enable);
            throw new Exception("Expects to be bool, {$type} given");
        }

        $previous = self::$strictMode;

        if ($enable !== null) {
            self::$strictMode = $enable;
        }

        return $previous;
    }

    /**
     * Force the `View::render` method to render at the time it is called
     */
    public static function forceRender()
    {
        self::$force = true;
    }

    /**
     * Starts rendering of registered views.
     * Executes all registered views and forces immediate rendering for subsequent calls.
     */
    public static function dispatch()
    {
        if (self::$force === false) {
            self::forceRender();

            foreach (self::$views as $value) {
                if ($value) {
                    self::render($value[0], $value[1], $value[2]);
                }
            }
        }
    }

    /**
     * Share or remove shared data to Views, shared variables will be
     * added as variables to the views that will be executed later
     *
     * @param string $key
     * @param mixed  $value
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
     * Check if view exists in `./application/View/` folder
     *
     * @param string $view
     * @return bool
     */
    public static function exists($view)
    {
        return inphinit_check_path(INPHINIT_SYSTEM . '/views/' . str_replace('.', '/', $view) . '.php');
    }

    /**
     * Register or render a View. If View is registered this method returns the index number from View
     *
     * @param string $view View path
     * @param array  $data Array that will be extracted to variables in the view
     * @param int    $mode Supported flags (may vary depending on the version):
     *                     - `ENT_COMPAT`
     *                     - `ENT_QUOTES`
     *                     - `ENT_NOQUOTES`
     *                     - `ENT_IGNORE`
     *                     - `ENT_SUBSTITUTE`
     *                     - `ENT_DISALLOWED`
     *                     - `ENT_HTML401`
     *                     - `ENT_XML1`
     *                     - `ENT_XHTML`
     *                     - `ENT_HTML5`
     * @return int|null
     */
    public static function render($view, array $data = array(), $mode = ENT_QUOTES)
    {
        $path = 'views/' . str_replace('.', '/', $view) . '.php';

        if (self::$strictMode && self::exists($view) === false) {
            throw new Exception($path . ' view not found (check case-sensitive)');
        }

        if (self::$force === false) {
            return array_push(self::$views, array($view, $data, $mode)) - 1;
        }

        $data += self::$shared;

        if ($mode !== self::UNSAFE) {
            self::escape($data, $mode);
        }

        inphinit_sandbox($path, $data);
    }

    /**
     * Remove a registered View by index
     *
     * @param int $index
     */
    public static function remove($index)
    {
        if (isset(self::$views[$index])) {
            unset(self::$views[$index]);
        }
    }

    private static function escape(array &$data, $mode)
    {
        foreach ($data as &$item) {
            if (is_array($item)) {
                self::escape($item, $mode);
            } elseif (is_string($item)) {
                $item = htmlspecialchars($item, $mode, self::$encoding);
            } elseif (is_object($item) && method_exists($item, '__toString')) {
                $item = htmlspecialchars((string) $item, $mode, self::$encoding);
            }
        }
    }
}
