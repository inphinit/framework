<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;

/**
 * Return normalized path (for checking case-sensitive in Windows OS)
 *
 * @param string $path
 * @return bool
 */
function UtilsCaseSensitivePath($path)
{
    return strtr($path, '\\', '/') === strtr(realpath($path), '\\', '/');
}

/**
 * Sandbox include files
 *
 * @param string $path
 * @return mixed
 */
function UtilsSandboxLoader($utilsSandBoxPath, array $utilsSandBoxData = array())
{
    if (empty($utilsSandBoxData) === false) {
        extract($utilsSandBoxData, EXTR_SKIP);
        $utilsSandBoxData = null;
    }

    return include INPHINIT_PATH . $utilsSandBoxPath;
}

/**
 * Use with `register_shutdown_function` fatal errors and execute `App::trigger('terminate')`
 *
 * @return void
 */
function UtilsShutDown()
{
    if (class_exists('\\Inphinit\\Viewing\\View', false)) {
        \Inphinit\Viewing\View::forceRender();
    }

    $e = error_get_last();

    if ($e !== null) {
        UtilsError($e['type'], $e['message'], $e['file'], $e['line'], null);
    }

    App::trigger('terminate');
}

/**
 * Get HTTP code from generate from server
 *
 * @return int
 */
function UtilsStatusCode()
{
    static $initial;

    if ($initial !== null) {
        return $initial;
    }

    $initial = 200;

    if (
        empty($_SERVER['PHP_SELF']) === false &&
        preg_match('#/RESERVED\.INPHINIT\-(\d{3})\.html$#', $_SERVER['PHP_SELF'], $match)
    ) {
        $initial = (int) $match[1];
    }

    return $initial;
}

/**
 * Get path from current project
 *
 * @return string
 */
function UtilsPath()
{
    static $pathinfo;

    if ($pathinfo !== null) {
        return $pathinfo;
    }

    $requri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $sname = $_SERVER['SCRIPT_NAME'];

    if ($requri !== $sname && $sname !== '/index.php') {
        $pathinfo = rtrim(strtr(dirname($sname), '\\', '/'), '/');
        $pathinfo = substr(urldecode($requri), strlen($pathinfo));
        $pathinfo = $pathinfo === false ? '/' : $pathinfo;
    } else {
        $pathinfo = urldecode($requri);
    }

    return $pathinfo;
}

/**
 * Alternative to composer-autoload
 *
 * @return void
 */
function UtilsAutoload()
{
    static $initiate;

    if ($initiate) {
        return null;
    }

    $initiate = true;
    $prefixes = require INPHINIT_PATH . 'boot/namespaces.php';

    if (is_array($prefixes) === false) {
        return null;
    }

    spl_autoload_register(function ($class) use ($prefixes) {

        $class = ltrim($class, '\\');

        $isfile = $base = false;

        if (isset($prefixes[$class]) && pathinfo($prefixes[$class], PATHINFO_EXTENSION)) {
            $isfile = true;
            $base = $prefixes[$class];
        } else {
            foreach ($prefixes as $prefix => $path) {
                if (stripos($class, $prefix) === 0) {
                    $class = substr($class, strlen($prefix));
                    $base = $path . '/' . strtr($class, substr($prefix, -1), '/');
                    break;
                }
            }
        }

        if ($base === false) {
            return null;
        }

        $path = preg_match('#^([a-z0-9]+:|/)#i', $base) ? $base : INPHINIT_PATH . $base;

        $files = $isfile ? array( $path ) : array( $path . '.php', $path . '.hh' );

        $files = array_filter($files, 'is_file');
        $files = array_shift($files);

        if ($files && UtilsCaseSensitivePath($files)) {
            include_once $files;
        }
    });
}

/**
 * Function used from `set_error_handler` and trigger `App::trigger('error')`
 *
 * @param int    $type
 * @param string $message
 * @param string $file
 * @param int    $line
 * @param array  $details
 * @return bool
 */
function UtilsError($type, $message, $file, $line, $details)
{
    static $preventDuplicate = '';

    $str  = '?' . $file . ':' . $line . '?';

    if (strpos($preventDuplicate, $str) === false) {
        $preventDuplicate .= $str;
        App::trigger('error', array($type, $message, $file, $line, $details));
    }

    return false;
}

/**
 * Bootstrapping application
 *
 * @return void
 */
function UtilsConfig()
{
    $url = dirname($_SERVER['SCRIPT_NAME']);
    $url = $url === '\\' ? '' : $url;

    define('INPHINIT_URL', $url . '/');
    define('REQUEST_TIME', time());
    define('EOL', chr(10));

    App::config('config');

    $dev = App::env('developer');

    error_reporting($dev ? E_ALL|E_STRICT : E_ALL & ~E_STRICT & ~E_DEPRECATED);
    ini_set('display_errors', $dev ? 1 : 0);

    register_shutdown_function('UtilsShutDown');
    set_error_handler('UtilsError', E_ALL|E_STRICT);

    header_remove('X-Powered-By');
}
