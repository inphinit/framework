<?php
/*
 * Experimental
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
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

            App::abort(500);
        }

        View::render(self::$views['error'], $data);
    }

    public static function renderPerformance()
    {
        if (empty(self::$views['performance'])) {
            return null;
        }

        View::render(self::$views['performance'], self::performance());
    }

    public static function renderClasses()
    {
        if (empty(self::$views['classes'])) {
            return null;
        }

        View::render(self::$views['classes'], array(
                'classes' => self::classes()
            ));
    }

    public static function view($type, $view)
    {
        if ($view !== null && View::exists($view) === false) {
            Exception::raise($view . ' view is not found', 2);
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
                Exception::raise($type . ' is not valid event', 2);
        }
    }

    public static function details($message, $file, $line)
    {
        $match = array();
        $oFile = $file;

        if (preg_match('#(.*?)\((\d+)\) : eval\(\)\'d code$#', trim($file), $match)) {
            $oFile = $match[1] . ' : eval():' . $line;
            $file  = $match[1];
            $line  = $match[2];
        }

        return array(
            'message' => $message,
            'file'    => $oFile,
            'line'    => $line,
            'source'  => $line > -1 ? self::source($file, $line) : null
        );
    }

    public static function performance()
    {
        return array(
            'usage' => memory_get_usage() / 1024,
            'peak'  => memory_get_peak_usage() / 1024,
            'real'  => memory_get_peak_usage(true) / 1024,
            'time'  => microtime(true) - INPHINIT_START
        );
    }

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

    public static function source($source, $line)
    {
        if ($line <= 0 || is_file($source) === false) {
            return null;
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
            'preview' => explode(EOL, File::portion($source, $init, $end, true))
        );
    }

    public static function caller($level = 0)
    {
        $trace = debug_backtrace(0);

        if ($level === -1) {
            return $trace;
        }

        if (empty($trace[$level])) {
            return false;
        }

        $file  = $trace[$level]['file'];
        $line  = $trace[$level]['line'];
        $trace = null;

        return array(
            'file' => $file,
            'line' => $line
        );
    }
}
