<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;

if (false === function_exists('http_response_code')) {
    /** Fallback for PHP 5.3.x */
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
 * @return array $sandbox_data
 */
function inphinit_sandbox($sandbox_path, array $sandbox_data = array())
{
    $sandbox_path = INPHINIT_PATH . $sandbox_path;

    if (inphinit_path_check($sandbox_path) === false) {
        return false;
    }

    if (empty($sandbox_data) === false) {
        extract($sandbox_data, EXTR_SKIP);
        $sandbox_data = null;
    }

    return include $sandbox_path;
}

/**
 * Use with `register_shutdown_function` fatal errors and execute `App::trigger('terminate')`
 *
 * @return void
 */
function inphinit_shutdown()
{
    if (class_exists('\\Inphinit\\Viewing\\View', false)) {
        \Inphinit\Viewing\View::forceRender();
    }

    $e = error_get_last();

    if ($e !== null) {
        inphinit_error($e['type'], $e['message'], $e['file'], $e['line']);
    }

    App::trigger('terminate');
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
    static $preventDuplicate = '';

    $str  = '?' . $file . ':' . $line . '?';

    if (strpos($preventDuplicate, $str) === false) {
        $preventDuplicate .= $str;
        App::trigger('error', array($type, $message, $file, $line));
    }

    return false;
}

/**
 * Check if file exists in public folder when using built-in server
 *
 * @param string $publicPath
 * @return bool
 */
function inphinit_file_builtin($publicPath)
{
    $path = INPHINIT_PATHINFO;

    return (
        $path !== '/' &&
        strpos($path, '.') !== 0 &&
        strpos($path, '/.') === false &&
        PHP_SAPI === 'cli-server' &&
        is_file($publicPath . $path)
    );
}

if (INPHINIT_COMPOSER) {
    require_once INPHINIT_PATH . 'vendor/autoload.php';
} else {
    $prefixes = require INPHINIT_PATH . 'boot/namespaces.php';

    spl_autoload_register(function ($class) use ($prefixes) {
        $class = ltrim($class, '\\');

        $isFile = $base = false;

        if (isset($prefixes[$class]) && pathinfo($prefixes[$class], PATHINFO_EXTENSION)) {
            $isFile = true;
            $base = $prefixes[$class];
        } else {
            foreach ($prefixes as $prefix => $path) {
                if (stripos($class, $prefix) === 0) {
                    $class = substr($class, strlen($prefix));
                    // substr($prefix, -1) check if is psr4 or psr0
                    $base = $path . '/' . strtr($class, substr($prefix, -1), '/');
                    break;
                }
            }
        }

        if ($base !== false) {
            if ($base[0] !== '/' && strpos($base, ':') === false) {
                $base = INPHINIT_PATH . $base;
            }

            if ($isFile === false) {
                $files = array_filter(array($base . '.php', $base . '.hh'), 'is_file');
                $base = array_shift($files);
            }

            if ($base && inphinit_path_check($base)) {
                include_once $base;
            }
        }
    });
}

$pathInfo = urldecode(strtok($_SERVER['REQUEST_URI'], '?'));

if (PHP_SAPI !== 'cli-server') {
    $pathInfo = substr($pathInfo, stripos($_SERVER['SCRIPT_NAME'], '/index.php'));
}

$url = dirname($_SERVER['SCRIPT_NAME']);

if ($url === '\\' || $url === '/') {
    $url = '';
}

define('INPHINIT_PATHINFO', $pathInfo);
define('INPHINIT_URL', $url . '/');
define('REQUEST_TIME', time());
define('EOL', chr(10));

foreach (inphinit_sandbox('application/Config/config.php') as $key => $value) {
    App::env($key, $value);
}

$dev = App::env('development');

if (function_exists('ini_set')) {
    ini_set('display_errors', $dev ? '1' : '0');
}

$reporting = $dev ? E_ALL|E_STRICT : E_ALL & ~E_STRICT & ~E_DEPRECATED;

error_reporting($reporting);

if (function_exists('init_set')) {
    ini_set('display_errors', $dev ? 1 : 0);
}

register_shutdown_function('inphinit_shutdown');

set_error_handler('inphinit_error', $reporting);

header_remove('X-Powered-By');

if (App::env('development')) {
    require_once INPHINIT_PATH . 'dev.php';
}

require_once INPHINIT_PATH . 'main.php';

App::exec();
