<?php
/*
 * Experimental
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\App;
use Inphinit\Http\Request;
use Inphinit\Http\Response;
use Inphinit\Viewing\View;

class Debug
{
    private static $loadConfigs = false;
    private static $showBeforeView = false;
    private static $displayErrors;
    private static $views = array();
    private static $fatal = array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_RECOVERABLE_ERROR);

    /**
     * Unregister debug events
     *
     * @return void
     */
    public static function unregister()
    {
        $nc = '\\' . get_called_class();

        App::off('error',     array( $nc, 'renderError' ));
        App::off('terminate', array( $nc, 'renderPerformance' ));
        App::off('terminate', array( $nc, 'renderClasses' ));

        if (false === empty(self::$displayErrors)) {
            function_exists('init_set') && ini_set('display_errors', self::$displayErrors);

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
        } elseif (preg_match('#allowed\s+memory\s+size\s+of\s+\d+\s+bytes\s+exhausted\s+\(tried\s+to\s+allocate\s+\d+\s+bytes\)#i', $message)) {
            die('<br><strong>Fatal error:</strong> ' . $message . ' in <strong>' . $file . '</strong> on line <strong>' . $line . '</strong>');
        }

        $data = self::details($type, $message, $file, $line);

        if (!headers_sent() && strcasecmp(Request::header('accept'), 'application/json') === 0) {
            ob_start();

            self::unregister();

            Response::cache(0);
            Response::type('application/json');

            echo json_encode($data);

            App::stop(500);
        }

        if (in_array($type, self::$fatal)) {
            View::dispatch();
        }

        self::render(self::$views['error'], $data);
    }

    /**
     * Render a View to show performance, memory and time to display page
     *
     * @return void
     */
    public static function renderPerformance()
    {
        if (!empty(self::$views['performance'])) {
            self::render(self::$views['performance'], self::performance());
        }
    }

    /**
     * Render a View to show performance and show declared classes
     *
     * @return void
     */
    public static function renderClasses()
    {
        if (!empty(self::$views['classes'])) {
            self::render(self::$views['classes'], array(
                'classes' => self::classes()
            ));
        }
    }

    /**
     * Register a debug views
     *
     * @param string $type
     * @param string $view
     *
     * @return void
     */
    public static function view($type, $view)
    {
        if ($view !== null && View::exists($view) === false) {
            throw new Exception($view . ' view is not found', 2);
        }

        $callRender = array( '\\' . get_called_class(), 'render' . ucfirst($type) );

        switch ($type) {
            case 'error':
                self::$views[$type] = $view;
                App::on('error', $callRender);

                if (empty(self::$displayErrors)) {
                    self::$displayErrors = ini_get('display_errors');

                    function_exists('ini_set') && ini_set('display_errors', '0');
                }
                break;

            case 'classes':
            case 'performance':
                self::$views[$type] = $view;
                App::on('terminate', $callRender);
                break;

            case 'before':
                self::$views[$type] = $view;
                break;

            default:
                throw new Exception($type . ' is not valid event', 2);
        }
    }

    private static function details($type, $message, $file, $line)
    {
        $match = array();
        $oFile = $file;

        if (preg_match('#called in ([\s\S]+?) on line (\d+)#', $message, $match)) {
            $file = $match[1];
            $line = (int) $match[2];
        }

        if (preg_match('#(.*?)\((\d+)\) : eval\(\)\'d code$#', trim($file), $match)) {
            $oFile = $match[1] . ' : eval():' . $line;
            $file  = $match[1];
            $line  = (int) $match[2];
        }

        switch ($type) {
            case E_PARSE:
                $message = 'Parse error: ' . $message;
                break;

            case E_DEPRECATED:
                $message = 'Deprecated: ' . $message;
                break;

            case E_ERROR:
            case E_USER_ERROR:
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
            'file'    => $oFile,
            'line'    => $line,
            'source'  => $line > -1 ? self::source($file, $line) : null
        );
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
            'peak'  => memory_get_peak_usage() / 1024,
            'real'  => memory_get_peak_usage(true) / 1024,
            'time'  => microtime(true) - INPHINIT_START
        );
    }

    /**
     * Get declared classes
     * @return array
     */
    public static function classes()
    {
        $objs = array();

        foreach (get_declared_classes() as $value) {
            $value = ltrim($value, '\\');
            $cname = new \ReflectionClass($value);

            if (false === $cname->isInternal()) {
                $objs[$value] = $cname->getDefaultProperties();
            }

            $cname = null;
        }

        return $objs;
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
            $init = $line - 5;
            $end  = $line + 5;
            $breakpoint = 6;
        } else {
            $init = 0;
            $end  = 5;
            $breakpoint = $line;
        }

        return array(
            'breakpoint' => $breakpoint,
            'preview' => preg_split('#\r\n|\n#',
                trim( File::portion($file, $init, $end, true), "\r\n")
            )
        );
    }

    /**
     * Get caller
     *
     * @param int $level
     * @return array|null
     */
    public static function caller($level = 0)
    {
        $trace = debug_backtrace(0);

        if ($level < 0) {
            return $trace;
        } elseif (empty($trace[$level])) {
            return null;
        } elseif (empty($trace[$level]['file'])) {
            $level = 1;
        }

        $file = $trace[$level]['file'];
        $line = $trace[$level]['line'];

        $trace = null;

        return array(
            'file' => $file,
            'line' => $line
        );
    }

    /**
     * Convert error message in a link, see `system/config/debug.php`
     *
     * @param string $message
     * @return string
     */
    public static function searcherror($message)
    {
        if (self::$loadConfigs === false) {
            App::config('debug');
            self::$loadConfigs = true;
        }

        $link = App::env('searcherror');

        if (strpos($link, '%error%') === -1) {
            return $message;
        }

        return preg_replace_callback('#^([\s\S]+?)\s+in\s+([\s\S]+?:\d+)|^([\s\S]+?)$#', function ($matches) use ($link)
        {
            $error = empty($matches[3]) ? $matches[1] : $matches[3];

            $url = str_replace('%error%', urlencode($error), $link);
            $url = htmlentities($url);

            return '<a target="_blank" href="' . $url . '">' . $error . '</a>' .
                    (empty($matches[2]) ? '' : (' in ' . $matches[2]));

        }, $message);
    }

    private static function render($view, $data)
    {
        if (!self::$showBeforeView && !empty(self::$views['before'])) {
            self::$showBeforeView = true;
            View::render(self::$views['before']);
        }

        View::render($view, $data);
    }
}
