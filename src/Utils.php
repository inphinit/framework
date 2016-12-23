<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;

function UtilsCaseSensitivePath($path)
{
    return $path === strtr(realpath($path), '\\', '/');
}

function UtilsSandboxLoader($utilsSandBoxPath, array $utilsSandBoxData = array())
{
    if (empty($utilsSandBoxData) === false) {
        extract($utilsSandBoxData, EXTR_SKIP);
        $utilsSandBoxData = null;
    }

    return include INPHINIT_PATH . $utilsSandBoxPath;
}

function UtilsShutDown()
{
    if (class_exists('\\Inphinit\\View', false)) {
        Inphinit\View::forceRender();
    }

    $e = error_get_last();

    if ($e !== null) {
        UtilsError($e['type'], $e['message'], $e['file'], $e['line'], null);
        $e = null;
    }

    App::trigger('terminate');
}

function UtilsStatusCode()
{
    static $initial;

    if ($initial !== null) {
        return $initial;
    }

    $initial = 200;

    if (empty($_SERVER['PHP_SELF']) === false &&
        preg_match('#/RESERVED\.INPHINIT\-(\d{3})\.html$#', $_SERVER['PHP_SELF'], $match) > 0)
    {
        $initial = (int) $match[1];
    }

    return $initial;
}

function UtilsPath()
{
    static $pathinfo;

    if ($pathinfo !== null) {
        return $pathinfo;
    }

    $sname  = $_SERVER['SCRIPT_NAME'];
    $requri = empty($_SERVER['REQUEST_URI']) ? null :
                preg_replace('#\?(.*)$#', '', $_SERVER['REQUEST_URI']);

    $pathinfo = rtrim(strtr(dirname($sname), '\\', '/'), '/');

    $pathinfo = substr(urldecode($requri), strlen($pathinfo) + 1);

    $pathinfo = '/' . ($pathinfo === false ? '' : $pathinfo);

    return $pathinfo;
}

function UtilsAutoload()
{
    static $initiate;

    if ($initiate) {
        return null;
    }

    $initiate = true;

    spl_autoload_register(function($classname)
    {
        static $prefixes;

        if (isset($prefixes) === false) {
            $path = INPHINIT_PATH . 'boot/namespaces.php';
            $prefixes = is_file($path) ? include $path : array();
        }

        $classname = ltrim($classname, '\\');
        $cp = array();

        if (is_array($prefixes) === false) {
            return NULL;
        }

        $isfile = false;
        $base = false;

        if (empty($prefixes) === false) {
            if (isset($prefixes[$classname]) && preg_match('#\.[a-z0-9]+$#', $prefixes[$classname]) !== 0) {
                $isfile = true;
                $base = $prefixes[$classname];
            } else {
                foreach ($prefixes as $prefix => $path) {
                    if (stripos($classname, $prefix) === 0) {
                        $classname = substr($classname, strlen($prefix));
                        $base = trim($path, '/') . '/' . str_replace(substr($prefix, -1), '/', $classname);
                        break;
                    }
                }
            }
        }

        if ($base === false) {
            return NULL;
        }

        $path = INPHINIT_PATH;

        $files = $isfile ? array( $path . $base ) :
                           array( $path . $base . '.php', $path . $base . '.hh' );

        $files = array_values(array_filter($files, 'is_file'));

        if (isset($files[0]) && UtilsCaseSensitivePath($files[0])) {
            include_once $files[0];
        }
    });
}

function UtilsError($type, $message, $file, $line, $details)
{
    static $preventDuplicate;

    $str  = '?' . $file . ':' . $line . '?';

    if ($preventDuplicate === null) {
        $preventDuplicate = '';
    }

    if (strpos($preventDuplicate, $str) === false) {
        $preventDuplicate .= $str;
        App::trigger('error', array($type, $message, $file, $line, $details));
    }

    return false;
}

function UtilsConfig()
{
    define('REQUEST_TIME', time());
    define('EOL', chr(10));

    App::config('config');

    $dev = App::env('developer') === true;

    error_reporting($dev ? E_ALL|E_STRICT : E_ALL & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', $dev ? 1 : 0);

    register_shutdown_function('UtilsShutDown');
    set_error_handler('UtilsError', E_ALL|E_STRICT);
}
