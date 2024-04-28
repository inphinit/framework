<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
    $utilsSandBoxPath = INPHINIT_PATH . $utilsSandBoxPath;

    if (UtilsCaseSensitivePath($utilsSandBoxPath) === false) {
        return false;
    }

    if (empty($utilsSandBoxData) === false) {
        extract($utilsSandBoxData, EXTR_SKIP);
        $utilsSandBoxData = null;
    }

    return include $utilsSandBoxPath;
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
        UtilsError($e['type'], $e['message'], $e['file'], $e['line']);
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

    if ($initial === null) {
        $initial = 200;

        if (preg_match('#/RESERVED\.INPHINIT\-(\d{3})\.html$#', $_SERVER['PHP_SELF'], $match)) {
            $initial = (int) $match[1];
        }
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

    if ($pathinfo === null) {
        $requri = urldecode(strtok($_SERVER['REQUEST_URI'], '?'));
        $sname = $_SERVER['SCRIPT_NAME'];
        $sdir = dirname($sname);

        if ($sdir !== '\\' && $sdir !== '/' && $requri !== $sname && $requri !== $sdir) {
            $sdir = rtrim($sdir, '/');
            $requri = substr($requri, strlen($sdir));
        }

        $pathinfo = $requri;
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

        if (preg_match('#^([a-z0-9]+:|/)#i', $base) === 0) {
            $base = INPHINIT_PATH . $base;
        }

        if ($isfile === false) {
            $files = array_filter(array( $base . '.php', $base . '.hh' ), 'is_file');
            $base = array_shift($files);
        }

        if ($base && UtilsCaseSensitivePath($base)) {
            include_once $base;
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
function UtilsError($type, $message, $file, $line, $details = null)
{
    static $preventDuplicate = '';

    $str  = '?' . $file . ':' . $line . '?';

    if (strpos($preventDuplicate, $str) === false) {
        $preventDuplicate .= $str;
        App::trigger('error', array($type, $message, $file, $line));
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

    if ($url === '\\' || $url === '/') {
        $url = '';
    }

    define('INPHINIT_URL', $url . '/');
    define('REQUEST_TIME', time());
    define('EOL', chr(10));

    foreach (UtilsSandboxLoader('application/Config/config.php') as $key => $value) {
        App::env($key, $value);
    }

    $dev = App::env('development');

    $reporting = $dev ? E_ALL|E_STRICT : E_ALL & ~E_STRICT & ~E_DEPRECATED;

    error_reporting($reporting);

    if (function_exists('init_set')) {
        ini_set('display_errors', $dev ? 1 : 0);
    }

    register_shutdown_function('UtilsShutDown');
    set_error_handler('UtilsError', $reporting);

    header_remove('X-Powered-By');
}
