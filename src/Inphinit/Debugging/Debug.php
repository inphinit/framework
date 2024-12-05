<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

use Inphinit\Config;
use Inphinit\Event;
use Inphinit\Exception;
use Inphinit\Filesystem\File;
use Inphinit\Http\Request;
use Inphinit\Http\Response;
use Inphinit\Viewing\View;

class Debug
{
    private $rendered = false;
    private $beforeView;
    private $views = array();
    private static $configs;

    /**
     * Set view for display displayed before other defined from a Debug instance
     *
     * @param string $view
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setBeforeView($view)
    {
        $this->beforeView = $view;
    }

    /**
     * Set view for display defined constants, functions and classes
     *
     * @param string $view
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setDefinedView($view)
    {
        $this->view('defined', $view);
    }

    /**
     * Set view for display errors and exceptions
     *
     * @param string $view
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setErrorView($view)
    {
        $this->view('error', $view);

        // Check functions are enabled
        if (function_exists('ini_get') && function_exists('ini_set')) {
            $config = ini_get('display_errors');

            if (empty($config) === false) {
                ini_set('display_errors', '0');
            }
        }
    }

    /**
     * Set view for display memory usage after application terminate
     *
     * @param string $view
     * @throws \Inphinit\Exception
     * @return void
     */
    public function setPerformanceView($view)
    {
        $this->view('performance', $view);
    }

    /**
     * Unregister debug events and views
     *
     * @return void
     */
    public function unregister()
    {
        foreach ($this->views as $type => $callback) {
            Event::off($type === 'error' ? $type : 'done', $callback);
        }

        ini_set('display_errors', '1');
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
     * @return array
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

        // Disable strict mode for File::lines, prevent extra-exceptions
        File::strictMode(false);

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
     * @return array
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

        return array();
    }

    /**
     * Convert error message in a link, see `system/configs/debug.php`
     *
     * @param string $message
     * @return string
     */
    public static function assistant($message)
    {
        self::boot();

        $link = self::$configs->assistant;

        if (strpos($link, '{error}') === false) {
            return $message;
        }

        $pos = strrpos($message, ' in ');

        if ($pos !== false) {
            $message = substr($message, 0, $pos);
        }

        $linkMessage = str_replace(array('"', '\''), '', $message);

        $link = str_replace('{error}', rawurlencode($linkMessage), $link);
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

        $link = false;
        $message = $file . ' in line ' . $line;
        $compareFile = str_replace('\\', '/', $file);

        /*
         * Note: The link to the editor will only be available for scripts outside the vendor, never edit a file on the vendor
         * Note: Probably the problem could be an error when using some lib and not in the lib
         * Note: The error could also be a bug in a library, report the bug
         */
        if (strpos($compareFile, INPHINIT_SYSTEM . '/vendor/') !== 0) {
            self::boot();

            $link = self::$configs->editor;

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

    private function view($type, $view)
    {
        self::boot();

        if (View::exists($view) === false) {
            throw new Exception($view . ' view is not found', 3);
        }

        $callback = function () use ($view, $type) {
            $args = func_get_args();
            array_unshift($args, $view);
            call_user_func_array(array($this, 'render' . ucfirst($type)), $args);
        };

        $this->views[$type] = $callback;

        Event::on($type === 'error' ? $type : 'done', $callback);
    }

    private function renderError($view, $type, $message, $file, $line)
    {
        if ($type === \E_ERROR && stripos(trim($message), 'allowed memory size') === 0) {
            die("Fatal error: {$message} in {$file} on line {$line}");
        }

        if (headers_sent() === false && strpos(Request::header('accept'), 'application/json') === 0) {
            $data = self::details($type, $file, $line, false);

            $this->unregister();

            Response::cache(0);
            Response::status(500);
            Response::content('application/json');

            echo json_encode($data);
            exit;
        }

        View::dispatch();

        $data = self::details($type, $message, $file, $line, true);

        $this->render($view, $data);
    }

    private function renderPerformance($view)
    {
        $this->render($view, self::performance());
    }

    private function renderDefined($view)
    {
        $this->render($view, array(
            'classes' => self::classes(),
            'constants' => self::constants(),
            'functions' => self::functions()
        ));
    }

    private function render($view, $data)
    {
        if ($this->rendered === false && $this->beforeView) {
            $this->rendered = true;
            View::render($this->beforeView);
        }

        View::render($view, $data);
    }

    private static function details($type, $message, $file, $line, $htmlentities = true)
    {
        $match = array();

        if (preg_match('#called in (.*?) on line (\d+)#', $message, $match)) {
            $file = $match[1];
            $line = (int) $match[2];
        }

        self::evalFileLocation($file, $line);

        switch ($type) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_RECOVERABLE_ERROR:
            case E_USER_ERROR: // deprecated as of PHP 8.4
                $message = 'Fatal error: ' . $message;
                break;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $message = 'Warning: ' . $message;
                break;

            case E_NOTICE:
            case E_USER_NOTICE:
                $message = 'Notice: ' . $message;
                break;

            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $message = 'Deprecated: ' . $message;
                break;

            case E_PARSE:
                $message = 'Parse error: ' . $message;
                break;
        }

        $source = null;

        if ($line > -1) {
            $source = self::source($file, $line);

            if ($htmlentities && $source) {
                foreach ($source['preview'] as &$entry) {
                    $entry = strtr($entry, array('<' => '&lt;', '>' => '&gt;'));
                }
            }
        }

        return array(
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'source' => $source
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

            self::$configs = new Config('debug');
            self::$configs->assistant; // Test
        }
    }
}
