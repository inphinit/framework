<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;

header_remove('X-Powered-By');

if (false === function_exists('http_response_code')) {
    /** Fallback for PHP 5.3 */
    function http_response_code($code = null)
    {
        static $current;

        if ($current === null) {
            if (preg_match('#/RESERVED\.INPHINIT-(\d{3})\.html#', $_SERVER['PHP_SELF'], $match)) {
                $current = (int) $match[1];
            } else {
                $current = 200;
            }
        }

        if ($code === null || $code === $current) {
            return $current;
        } elseif (headers_sent() || $code < 100 || $code > 599) {
            return false;
        }

        header('X-PHP-Response-Code: ' . $code, true, $code);

        $lastCode = $current;

        $current = $code;

        return $lastCode;
    }
}

/**
 * Return normalized path (for checking case-sensitive in Windows OS)
 *
 * @param string $path
 * @return bool
 */
function inphinit_path_check($path)
{
    return strtr($path, '\\', '/') === strtr(realpath($path), '\\', '/');
}

/**
 * Sandbox include files
 *
 * @param string $sandbox_path
 * @param array  $sandbox_data
 */
function inphinit_sandbox($sandbox_path, array &$sandbox_data = null)
{
    $sandbox_path = INPHINIT_PATH . $sandbox_path;

    if (inphinit_path_check($sandbox_path)) {
        if ($sandbox_data) {
            extract($sandbox_data, EXTR_SKIP);
        }

        return include $sandbox_path;
    }
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
function inphinit_error($type, $message, $file, $line, $details = null)
{
    static $preventDuplicate = array();

    $file  = $file . ':' . $line;

    if (strpos($preventDuplicate, $str) === false) {
        $preventDuplicate[] = $str;
        App::trigger('error', array($type, $message, $file, $line));
    }

    return false;
}

/**
 * Use with `register_shutdown_function` fatal errors and execute `App::trigger('terminate')`
 *
 * @return void
 */
function inphinit_shutdown()
{
    $e = error_get_last();

    if ($e !== null) {
        App::dispatch();
        inphinit_error($e['type'], $e['message'], $e['file'], $e['line']);
    }

    App::trigger('terminate');
}

if (INPHINIT_COMPOSER) {
    require_once INPHINIT_PATH . 'vendor/autoload.php';
} else {
    $prefixes = require INPHINIT_PATH . 'boot/namespaces.php';

    spl_autoload_register(function ($class) use ($prefixes) {
        $class = ltrim($class, '\\');

        $base = null;

        if (isset($prefixes[$class]) && pathinfo($prefixes[$class], PATHINFO_EXTENSION)) {
            $base = $prefixes[$class];
        } else {
            foreach ($prefixes as $prefix => $path) {
                if (stripos($class, $prefix) === 0) {
                    $class = substr($class, strlen($prefix));
                    // About substr($prefix, -1), if it returns "\" it is PSR 4, otherwise it returns "_" it is PSR 0
                    $base = $path . '/' . strtr($class, substr($prefix, -1), '/') . '.php';
                    break;
                }
            }
        }

        if ($base !== null) {
            // if starts with / or contains :, $base request a file
            if ($base[0] !== '/' && strpos($base, ':') === false) {
                $base = INPHINIT_PATH . $base;
            }

            if (inphinit_path_check($base)) {
                include_once $base;
            }
        }
    });
}

$pathInfo = urldecode(strtok($_SERVER['REQUEST_URI'], '?'));

if (PHP_SAPI !== 'cli-server') {
    $pathInfo = substr($pathInfo, stripos($_SERVER['SCRIPT_NAME'], '/index.php'));
}

$urlInfo = dirname($_SERVER['SCRIPT_NAME']);

if ($urlInfo === '\\' || $urlInfo === '/') {
    $urlInfo = '';
}

define('INPHINIT_PATHINFO', $pathInfo);
define('INPHINIT_URL', $urlInfo . '/');
define('REQUEST_TIME', time());
define('EOL', chr(10));

require 'Inphinit/App.php';
require 'Inphinit/Routing/Route.php';

foreach (inphinit_sandbox('application/Config/config.php') as $key => $value) {
    App::env($key, $value);
}

$dev = App::env('development');

register_shutdown_function('inphinit_shutdown');

set_error_handler('inphinit_error', $dev ? E_ALL|E_STRICT : error_reporting());

if ($dev) {
    require_once INPHINIT_PATH . 'dev.php';
}

require_once INPHINIT_PATH . 'main.php';

App::exec();
