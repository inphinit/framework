<?php
/*
 * Experimental
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\App;
use Inphinit\View;
use Inphinit\Request;
use Inphinit\Response;

class Debug
{
    private static $views = array();
    private static $displayErrors;

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
            ini_set('display_errors', self::$displayErrors);

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
        }

        $data = self::details($message, $file, $line);

        if (!headers_sent() && Request::is('xhr')) {
            ob_start();

            self::unregister();

            Response::cache(0);
            Response::type('application/json');

            echo json_encode($data);

            App::stop(500);
        }

        if ($type === E_ERROR || $type === E_PARSE || $type === E_RECOVERABLE_ERROR) {
            View::forceRender();
        }

        View::render(self::$views['error'], $data);
    }

    /**
     * Render a View to show performance, memory and time to display page
     *
     * @return void
     */
    public static function renderPerformance()
    {
        if (empty(self::$views['performance'])) {
            return null;
        }

        View::render(self::$views['performance'], self::performance());
    }

    /**
     * Render a View to show performance and show declared classes
     *
     * @return void
     */
    public static function renderClasses()
    {
        if (empty(self::$views['classes'])) {
            return null;
        }

        View::render(self::$views['classes'], array(
            'classes' => self::classes()
        ));
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

                    ini_set('display_errors', '0');
                }
            break;

            case 'classes':
            case 'performance':
                self::$views[$type] = $view;
                App::on('terminate', $callRender);
            break;

            default:
                throw new Exception($type . ' is not valid event', 2);
        }
    }

    /**
     * Get detailed from error, include eval errors
     *
     * @param string $message
     * @param string $file
     * @param int    $line
     * @return array
     */
    public static function details($message, $file, $line)
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
        $listClasses = get_declared_classes();

        foreach ($listClasses as $value) {
            $value = ltrim($value, '\\');
            $cname = new \ReflectionClass($value);

            if (false === $cname->isInternal()) {
                $objs[$value] = $cname->getDefaultProperties();
            }

            $cname = null;
        }

        $listClasses = null;

        return $objs;
    }

    /**
     * Get snippet from a file
     *
     * @param string $file
     * @param int    $line
     * @return array|bool
     */
    public static function source($file, $line)
    {
        if ($line <= 0 || is_file($file) === false) {
            return false;
        } elseif ($line >= 5) {
            $init = $line - 5;
            $end  = $line + 5;
            $breakpoint = 5;
        } else {
            $init = 0;
            $end  = 5;
            $breakpoint = $line;
        }

        return array(
            'breakpoint' => $breakpoint,
            'preview' => explode(EOL, File::portion($file, $init, $end, true))
        );
    }

    /**
     * Get caller
     *
     * @param int $level
     * @return array|bool
     */
    public static function caller($level = 0)
    {
        $trace = debug_backtrace(0);

        if ($level < 0) {
            return $trace;
        }

        if (empty($trace[$level])) {
            return false;
        }

        if (empty($trace[$level]['file'])) {
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
}
