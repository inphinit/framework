<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Diagnostics;

use Inphinit\Config;
use Inphinit\Event;
use Inphinit\Exception;
use Inphinit\Filesystem\File;
use Inphinit\Http\Request;
use Inphinit\Http\Response;
use Inphinit\Viewing\View;

class Debug
{
    /** @var array<string, string> List of shortcuts to link errors to external assistants */
    protected static $assistants = array(
        'chatgpt' => 'https://chat.openai.com/?q={error}',
        'claude' => 'https://claude.ai/new?q={error}',
        'duck.ai' => 'https://duckduckgo.com/?q={error}&amp;ia=chat',
        'duckduckgo' => 'https://duckduckgo.com/?q={error}',
        'google' => 'https://www.google.com/search?q={error}',
        'google.ai' => 'https://www.google.com/search?q={error}&amp;udm=50',
        'perplexity' => 'https://www.perplexity.ai/search?q={error}'
    );

    /** @var array<string, string> List of shortcuts for linking problematic files via link to external editors */
    protected static $editors = array(
        // Requires: https://packagecontrol.io/packages/subl%20protocol
        'sublimetext' => 'subl://{path}:{line}',
        'vscode' => 'vscode://file/{path}:{line}:0',
    );

    private $rendered = false;
    private $beforeView;
    private $views = array();
    private static $configs;

    /**
     * Set view for display displayed before other defined from a Debug instance
     * Note: This method does not affect behavior in the CLI environment
     *
     * @param string $view
     */
    public function setBeforeView($view)
    {
        if (PHP_SAPI !== 'cli') {
            $this->beforeView = $view;
        }
    }

    /**
     * Set view for display defined constants, functions and classes
     * Note: This method does not affect behavior in the CLI environment
     *
     * @param string $view
     * @throws \Inphinit\Exception
     */
    public function setDefinedView($view)
    {
        $this->setView('defined', $view);
    }

    /**
     * Set view for display errors and exceptions
     * Note: This method does not affect behavior in the CLI environment
     *
     * @param string $view
     * @throws \Inphinit\Exception
     */
    public function setErrorView($view)
    {
        $this->setView('error', $view);

        // Check functions are enabled
        if (PHP_SAPI !== 'cli' && function_exists('ini_get') && function_exists('ini_set')) {
            $config = ini_get('display_errors');

            if (empty($config) === false) {
                ini_set('display_errors', '0');
            }
        }
    }

    /**
     * Set view for display memory usage after application terminate
     * Note: This method does not affect behavior in the CLI environment
     *
     * @param string $view
     * @throws \Inphinit\Exception
     */
    public function setPerformanceView($view)
    {
        $this->setView('performance', $view);
    }

    /**
     * Unregister debug events and views
     */
    public function unregister()
    {
        foreach ($this->views as $type => $callback) {
            Event::off($type === 'error' ? $type : 'done', $callback);
        }

        if (function_exists('ini_set')) {
            ini_set('display_errors', '1');
        }
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

        if (empty($data['user'])) {
            return array();
        }

        $data = $data['user'];

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
        $data = get_defined_constants(true);

        if (empty($data['user'])) {
            return array();
        }

        $data = $data['user'];

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
     * Convert error message into a link for a search engine or
     * online assistant to analyze the error message. See `system/configs/debug.php`
     *
     * @param string $message
     * @param string $target
     * @return string
     */
    public static function assistant($message, $target = '_blank')
    {
        self::boot();

        $link = null;

        $option = self::$configs->assistant;

        if ($option) {
            $link = isset(self::$assistants[$option]) ? self::$assistants[$option] : $option;
        }

        if ($link && strpos($link, '{error}') !== false) {
            $pos = strrpos($message, ' in ');

            if ($pos !== false) {
                $message = substr($message, 0, $pos);
            }

            $linkMessage = html_entity_decode($message);
            $linkMessage = str_replace(array('"', '\''), '', $linkMessage);
            $linkMessage = rawurlencode($linkMessage);

            $link = str_replace('{error}', $linkMessage, $link);

            return '<a rel="nofollow noreferrer" target="' . $target . '" href="' . $link . '">' . $message . '</a>';
        }

        return $message;
    }

    /**
     * Creates a link to open a file in your editor via the protocol. See `system/configs/debug.php`
     *
     * @param string $file
     * @param int $line
     * @param string $target
     * @return string
     */
    public static function editor($file, $line, $target = '_self')
    {
        self::boot();

        $link = null;
        $message = $file . ' in line ' . $line;

        $file = realpath($file);
        $file = str_replace('\\', '/', $file);
        $line = (string) $line;

        $vendor = realpath(INPHINIT_SYSTEM . '/vendor/');
        $vendor = str_replace('\\', '/', $vendor);

        /*
         * Note: The link to the editor will only be available for scripts outside the vendor
         * Note: Probably the problem could be an error when using some lib and not in the lib
         * Note: The error could also be a bug in a library, report the bug
         */
        if (strpos($file, $vendor) !== 0) {
            $option = self::$configs->editor;

            if ($option) {
                $link = isset(self::$editors[$option]) ? self::$editors[$option] : $option;
            }
        }

        if ($link && strpos($link, '{path}') !== false) {
            $file = html_entity_decode($file);
            $file = rawurlencode($file);

            // Restores the directory separator
            $file = str_replace('%2F', '/', $file);

            $link = str_replace('{path}', $file, $link);
            $link = str_replace('{line}', $line, $link);

            return '<a rel="nofollow noreferrer" target="' . $target . '" href="' . $link . '">' . $message . '</a>';
        }

        return $message;
    }

    private function setView($type, $view)
    {
        self::boot();

        if (PHP_SAPI !== 'cli') {
            if (View::exists($view) === false) {
                throw new Exception($view . ' view not found', 0, 3);
            }

            $callback = function () use ($view, $type) {
                $args = func_get_args();
                array_unshift($args, $view);
                call_user_func_array(array($this, 'render' . ucfirst($type)), $args);
            };

            $this->views[$type] = $callback;

            Event::on($type === 'error' ? $type : 'done', $callback);
        }
    }

    private function renderError($view, $type, $message, $file, $line)
    {
        if ($type === \E_ERROR && stripos(trim($message), 'allowed memory size') === 0) {
            die("Fatal error: {$message} in {$file} on line {$line}");
        }

        $data = self::details($type, $message, $file, $line);

        if (headers_sent() === false && strpos(Request::header('accept'), 'application/json') === 0) {
            $this->unregister();

            Response::cache(0);
            Response::status(500);
            Response::type('application/json');

            echo json_encode($data);
            exit;
        }

        View::dispatch();

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

    private static function details($type, $message, $file, $line)
    {
        $match = array();

        if (preg_match('#called\s+in\s+(.*?)\s+on\s+line\s+(\d+)(\s+)?$#', $message, $match)) {
            $file = $match[1];
            $line = (int) $match[2];
        }

        Inspector::evalSource($file, $file, $line);

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
        }

        return array(
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'source' => $source
        );
    }

    /** some errors prevent spl_autoload from continuing, so it is necessary to include */
    private static function boot()
    {
        if (self::$configs === null) {
            include_once __DIR__ . '/Inspector.php';
            include_once __DIR__ . '/../Config.php';
            include_once __DIR__ . '/../Event.php';
            include_once __DIR__ . '/../Exception.php';
            include_once __DIR__ . '/../Filesystem/File.php';
            include_once __DIR__ . '/../Http/Request.php';
            include_once __DIR__ . '/../Http/Response.php';
            include_once __DIR__ . '/../Viewing/View.php';

            self::$configs = new Config('debug');
            self::$configs->assistant; // Test
        }
    }
}
