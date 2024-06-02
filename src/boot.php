<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
use Inphinit\Event;

header_remove('X-Powered-By');

/**
 * Return normalized path (for checking case-sensitive in Windows OS)
 *
 * @param string $path
 * @return bool
 */
function inphinit_path_check($path)
{
    return str_replace('\\', '/', $path) === str_replace('\\', '/', realpath($path));
}

/**
 * Sandbox include files
 *
 * @param string $sandbox_path
 * @param array  $sandbox_data
 */
function inphinit_sandbox($sandbox_path, array &$sandbox_data = null)
{
    $sandbox_path = INPHINIT_SYSTEM . '/' . $sandbox_path;

    if (inphinit_path_check($sandbox_path)) {
        if ($sandbox_data) {
            extract($sandbox_data, EXTR_SKIP);
        }

        return include $sandbox_path;
    }
}

/**
 * Function used from `set_error_handler` and trigger `Event::trigger('error')`
 *
 * @param int    $type
 * @param string $message
 * @param string $file
 * @param int    $line
 * @param array  $context
 * @return bool
 */
function inphinit_error($type, $message, $file, $line, $context = null)
{
    static $collectedErrors = array();

    $collect = $file . ':' . $line;

    if (in_array($collect, $collectedErrors) === false) {
        $collectedErrors[] = $collect;

        if (class_exists('\\Inphinit\\Event', false)) {
            Event::trigger('error', array($type, $message, $file, $line));
        }
    }

    return false;
}

/**
 * Use with `register_shutdown_function` fatal errors and execute `done` event
 */
function inphinit_shutdown()
{
    $last = error_get_last();

    if ($last !== null && (error_reporting() & $last['type'])) {
        App::forward();
        inphinit_error($last['type'], $last['message'], $last['file'], $last['line']);
    }

    if (class_exists('\\Inphinit\\Event', false)) {
        Event::trigger('done');
    }
}

set_error_handler('inphinit_error', error_reporting());

register_shutdown_function('inphinit_shutdown');

if (INPHINIT_COMPOSER) {
    require_once INPHINIT_SYSTEM . '/vendor/autoload.php';
} else {
    $prefixes = require INPHINIT_SYSTEM . '/boot/namespaces.php';

    spl_autoload_register(function ($class) use (&$prefixes) {
        $class = ltrim($class, '\\');

        $base = null;

        if (isset($prefixes[$class]) && pathinfo($prefixes[$class], PATHINFO_EXTENSION)) {
            $base = $prefixes[$class];
        } else {
            foreach ($prefixes as $prefix => $path) {
                if (stripos($class, $prefix) === 0) {
                    $class = substr($class, strlen($prefix));
                    // substr($prefix, -1) returns \ (PSR-4) or _ (PSR-0)
                    $base = $path . '/' . str_replace(substr($prefix, -1), '/', $class) . '.php';
                    break;
                }
            }
        }

        if ($base !== null) {
            // if starts with / or contains :, $base request a file
            if ($base[0] !== '/' && strpos($base, ':') === false) {
                $base = INPHINIT_SYSTEM . '/' . $base;
            }

            if (inphinit_path_check($base)) {
                include_once $base;
            }
        }
    });
}

require 'Inphinit/App.php';

$app = new App;

if (App::config('development')) {
    require 'development.php';
}

require INPHINIT_SYSTEM . '/main.php';
