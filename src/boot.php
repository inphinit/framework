<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;

header_remove('X-Powered-By');

require 'Inphinit/App.php';
require 'Inphinit/Routing/Router.php';
require 'Inphinit/Routing/Route.php';

/**
 * case-sensitive check path
 *
 * @param string $path
 * @return bool
 */
function inphinit_check_path($path)
{
    return realpath($path) === str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
}

/**
 * Sandbox include files
 *
 * @param string $sandbox_path
 * @param array  $sandbox_data
 * @return mixed
 */
function inphinit_sandbox($sandbox_path, array $sandbox_data = array())
{
    $sandbox_path = INPHINIT_SYSTEM . '/' . $sandbox_path;

    if (inphinit_check_path($sandbox_path)) {
        if ($sandbox_data) {
            extract($sandbox_data, EXTR_SKIP);
        }

        return include $sandbox_path;
    }
}

/**
 * Function used by `set_error_handler` and `App::trigger('error')`
 *
 * @param int    $type
 * @param string $message
 * @param string $file
 * @param int    $line
 * @param array  $context
 * @return false
 */
function inphinit_error($type, $message, $file, $line, $context = null)
{
    static $collectedErrors = array();

    $collect = $file . ':' . $line;

    if (in_array($collect, $collectedErrors) === false) {
        $collectedErrors[] = $collect;

        if (error_reporting() & $type) {
            App::trigger('error', array($type, $message, $file, $line));
        }
    }

    return false;
}

set_error_handler('inphinit_error');

register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null) {
        App::dispatch();
        inphinit_error($error['type'], $error['message'], $error['file'], $error['line']);
    }
});

if (INPHINIT_COMPOSER) {
    require_once INPHINIT_SYSTEM . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        static $prefixes;

        if ($prefixes === null) {
            $prefixes = require INPHINIT_SYSTEM . '/boot/namespaces.php';
        }

        $class = ltrim($class, '\\');

        if (isset($prefixes[$class]) && pathinfo($prefixes[$class], PATHINFO_EXTENSION)) {
            $base = $prefixes[$class];
        } else {
            $base = null;

            foreach ($prefixes as $prefix => $path) {
                if (stripos($class, $prefix) === 0) {
                    $class = substr($class, strlen($prefix));
                    // substr($prefix, -1) -> '\' (PSR-4) or '_' (PSR-0)
                    $base = $path . '/' . str_replace(substr($prefix, -1), '/', $class) . '.php';
                    break;
                }
            }
        }

        if ($base !== null) {
            // if $base does not start with '/' nor contain ':', $base will request a file
            if ($base[0] !== '/' && strpos($base, ':') === false) {
                $base = INPHINIT_SYSTEM . '/' . $base;
            }

            if (inphinit_check_path($base)) {
                include_once $base;
            }
        }
    });
}

$inphinit_path = rawurldecode(strtok($_SERVER['REQUEST_URI'], '?'));

if (PHP_SAPI !== 'cli-server') {
    $inphinit_path = substr($inphinit_path, strpos($_SERVER['SCRIPT_NAME'], '/index.php'));
}

define('INPHINIT_PATH', $inphinit_path);
define('REQUEST_TIME', time());

if (App::config('development')) {
    require 'development.php';
}

require INPHINIT_SYSTEM . '/main.php';
