<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

use Inphinit\App;
use Inphinit\Config;
use Inphinit\Event;
use Inphinit\Exception;
use Inphinit\Filesystem\File;
use Inphinit\Http\Request;
use Inphinit\Http\Response;
use Inphinit\Viewing\View;

class Debug
{
    private static $showBeforeView = false;
    private static $displayErrors;
    private static $views = array();
    private static $configs;

    /**
     * Unregister debug events
     *
     * @return void
     */
    public static function unregister()
    {
        $nc = '\\' . get_called_class();

        Event::off('error', array($nc, 'renderError'));
        Event::off('done', array($nc, 'renderPerformance'));
        Event::off('done', array($nc, 'renderDefined'));

        if (empty(self::$displayErrors) === false) {
            if (function_exists('init_set')) {
                ini_set('display_errors', self::$displayErrors);
            }

            self::$displayErrors = null;
        }
    }

    /**
     * Render a View to error
     *
     * @param int    $type
     * @param string $message
     * @param string $file
     * @param int    $line
     * @return void
     */
    public static function renderError($type, $message, $file, $line)
    {
        if (empty(self::$views['error'])) {
            return null;
        } elseif ($type === E_ERROR && stripos(trim($message), 'allowed memory size') === 0) {
            die("Fatal error: {$message} in {$file} on line {$line}");
        }

        $data = self::details($type, $message, $file, $line);

        if (headers_sent() === false && strpos(Request::header('accept'), 'application/json') === 0) {
            self::unregister();

            Response::cache(0);
            Response::status(500);
            Response::content('application/json');

            echo json_encode($data);
            exit;
        }

        View::dispatch();

        self::render(self::$views['error'], $data);
    }

    /**
     * Render a View to show performance, memory and time to display page
     *
     * @return void
     */
    public static function renderPerformance()
    {
        if (isset(self::$views['performance'])) {
            self::render(self::$views['performance'], self::performance());
        }
    }

    /**
     * Render a View to show performance and show declared classes
     *
     * @return void
     */
    public static function renderDefined()
    {
        if (isset(self::$views['defined'])) {
            self::render(self::$views['defined'], array(
                'classes' => self::classes(),
                'constants' => self::constants(),
                'functions' => self::functions()
            ));
        }
    }

    /**
     * Register a debug views
     *
     * @param string $type
     * @param string $view
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function view($type, $view)
    {
        self::boot();

        if ($view !== null && View::exists($view) === false) {
            throw new Exception($view . ' view is not found', 0, 2);
        }

        $callRender = array('\\' . get_called_class(), 'render' . ucfirst($type));

        if ($type === 'error') {
            Event::on('error', $callRender);

            if (empty(self::$displayErrors)) {
                self::$displayErrors = ini_get('display_errors');

                if (function_exists('ini_set')) {
                    ini_set('display_errors', '0');
                }
            }
        } elseif ($type === 'defined' || $type === 'performance') {
            Event::on('done', $callRender);
        } elseif ($type !== 'before') {
            throw new Exception($type . ' is not valid event', 0, 2);
        }

        self::$views[$type] = $view;
    }

    /**
     * Get memory usage and you can also use it to calculate runtime.
     *
     * @return array
     */
    public static function performance()
    {
        return array(
            'usage' => memory_get_usage() / 1024,
            'peak' => memory_get_peak_usage() / 1024,
            'real' => memory_get_peak_usage(true) / 1024,
            'time' => microtime(true) - INPHINIT_START
        );
    }

    /**
     * Get declared classes
     * @return array
     */
    public static function classes()
    {
        $data = get_declared_classes();

        foreach ($data as $index => $current) {
            $current = ltrim($current, '\\');
            $cname = new \ReflectionClass($current);

            if ($cname->isInternal()) {
                unset($data[$index]);
            }
        }

        sort($data);

        return $data;
    }

    /**
     * Get declared functions
     *
     * @return array
     */
    public static function functions()
    {
        $data = get_defined_functions()['user'];

        sort($data);

        return $data;
    }

    /**
     * Get defined constants
     *
     * @return array
     */
    public static function constants()
    {
        $data = get_defined_constants(true)['user'];

        ksort($data);

        return $data;
    }

    /**
     * Get snippet from a file
     *
     * @param string $file
     * @param int    $line
     * @return array|null
     */
    public static function source($file, $line)
    {
        if ($line <= 0 || is_file($file) === false) {
            return null;
        } elseif ($line > 5) {
            $init = $line - 6;
            $max = 10;
            $breakpoint = 6;
        } else {
            $init = 0;
            $max = 5;
            $breakpoint = $line;
        }

        $preview = preg_split('#\r\n|\n#', File::lines($file, $init, $max));

        if (count($preview) !== $breakpoint && trim(end($preview)) === '') {
            array_pop($preview);
        }

        return array(
            'breakpoint' => $breakpoint,
            'preview' => $preview
        );
    }

    /**
     * Get backtrace php scripts
     *
     * @param int $level
     * @param int $limit
     * @return array|null
     */
    public static function caller($level = 0, $limit = 100)
    {
        $trace = debug_backtrace(0, $limit);

        foreach ($trace as $key => &$value) {
            if (isset($value['file'])) {
                self::evalFileLocation($value['file'], $value['line']);
            } else {
                unset($trace[$key]);
            }
        }

        $trace = array_values($trace);

        if ($level < 0) {
            return $trace;
        } elseif (isset($trace[$level])) {
            return $trace = $trace[$level];
        }
    }

    /**
     * Convert error message in a link, see `system/configs/debug.php`
     *
     * @param string $message
     * @return string
     */
    public static function searcher($message)
    {
        self::boot();

        $link = self::$configs->get('searcherror');

        if (strpos($link, '{error}') === -1) {
            return $message;
        }

        $pos = strrpos($message, ' in ');

        if ($pos !== false) {
            $message = substr($message, 0, $pos);
        }

        $link = str_replace('{error}', rawurlencode($message), $link);
        $link = htmlentities($link);
        $message = htmlentities($message);

        return '<a rel="nofollow noreferrer" target="_blank" href="' . $link . '">' . $message . '</a>';
    }

    /**
     * Convert error message in a link, see `system/configs/debug.php`
     *
     * @param string $file
     * @param int $line
     * @return string
     */
    public static function editor($file, $line)
    {
        self::boot();

        $message = $file . ' on line ' . $line;
        $link = false;
        $compareFile = str_replace('\\', '/', $file);

        /*
         * Note: The link to the editor will only be available for scripts outside the vendor, never edit a file on the vendor
         * Note: Probably the problem could be an error when using some lib and not in the lib
         * Note: The error could also be a bug in a library, report the bug
         */
        if (strpos($compareFile, INPHINIT_SYSTEM . '/vendor/') !== 0) {
            self::boot();

            $link = self::$configs->get('editor');

            switch ($link) {
                case 'vscode':
                    $link = 'vscode://file/{path}:{line}:0';
                    break;
                case 'sublimetext':
                    // Requires: https://packagecontrol.io/packages/subl%20protocol
                    $link = 'subl://{path}:{line}';
                    break;
            }
        }

        if ($link && strpos($link, '{path}') !== -1) {
            $link = str_replace('{path}', rawurlencode($file), $link);
            $link = str_replace('{line}', rawurlencode($line), $link);
            $message = '<a rel="nofollow noreferrer" href="' . $link . '">' . $message . '</a>';
        }

        return $message;
    }

    private static function render($view, $data)
    {
        if (!self::$showBeforeView && isset(self::$views['before'])) {
            self::$showBeforeView = true;
            View::render(self::$views['before']);
        }

        View::render($view, $data);
    }

    private static function details($type, $message, $file, $line)
    {
        $match = array();

        if (preg_match('#called in (.*?) on line (\d+)#', $message, $match)) {
            $file = $match[1];
            $line = (int) $match[2];
        }

        self::evalFileLocation($file, $line);

        switch ($type) {
            case E_PARSE:
                $message = 'Parse error: ' . $message;
                break;

            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $message = 'Deprecated: ' . $message;
                break;

            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $message = 'Fatal error: ' . $message;
                break;

            case E_WARNING:
            case E_USER_WARNING:
                $message = 'Warning: ' . $message;
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
                $message = 'Notice: ' . $message;
                break;
        }

        return array(
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'source' => $line > -1 ? self::source($file, $line) : null
        );
    }

    private static function evalFileLocation(&$file, &$line)
    {
        if (preg_match('#(.*?)\((\d+)\) : eval\(\)\'d code#', $file, $match)) {
            $file = $match[1];
            $line = (int) $match[2];
        }
    }

    /** some errors prevent spl_autoload from continuing, so it is necessary to include */
    private static function boot()
    {
        if (self::$configs === null) {
            include_once __DIR__ . '/../Config.php';
            include_once __DIR__ . '/../Exception.php';
            include_once __DIR__ . '/../Filesystem/File.php';
            include_once __DIR__ . '/../Http/Request.php';
            include_once __DIR__ . '/../Http/Response.php';

            self::$configs = Config::load('debug');
            self::$configs->get('*'); // Test
        }
    }
}
