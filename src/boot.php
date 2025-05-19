<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
use Inphinit\Event;

header_remove('X-Powered-By');

require 'Inphinit/App.php';

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
function inphinit_sandbox($sandbox_path, &$sandbox_data = null)
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
 * Function used by `set_error_handler` and `Event::trigger('error')`
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

        if (class_exists('\\Inphinit\\Event', false) && (error_reporting() & $type)) {
            Event::trigger('error', array($type, $message, $file, $line));
        }
    }

    return false;
}

set_error_handler('inphinit_error');

register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null) {
        App::forward();
        inphinit_error($error['type'], $error['message'], $error['file'], $error['line']);
    }
});

$inphinit_config_development = App::config('development');

if (INPHINIT_COMPOSER) {
    require_once INPHINIT_SYSTEM . '/vendor/autoload.php';
} else {
    if (!$inphinit_config_development) {
        /*
         * Improved autoload performance for classes from system folder (Controllers, Models, Services, ...)
         * and classes within the Inphinit\ namespace
         * Nota: Only available in production mode, in developer mode it will do extra checks to avoid failures
         */
        set_include_path(__DIR__ . PATH_SEPARATOR . INPHINIT_SYSTEM);
        spl_autoload_extensions('.php');
        spl_autoload_register();
    }

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

if (PHP_SAPI === 'cli') {
    return null;
}

$inphinit_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

$inphinit_proto = App::config('fowarded_proto');
$inphinit_host = App::config('fowarded_host');
$inphinit_port = App::config('fowarded_port');

if ($inphinit_proto === null) {
    $inphinit_proto = $inphinit_https ? 'https' : 'http';
}

if ($inphinit_host === null && isset($_SERVER['HTTP_HOST'])) {
    $inphinit_host = $_SERVER['HTTP_HOST'];
}

$inphinit_port_header = false;

if ($inphinit_host) {
    $inphinit_host = strtok($inphinit_host, ':');
    $inphinit_port_header = strtok(':');
}

if ($inphinit_port === null) {
    $inphinit_port = $inphinit_port_header ? $inphinit_port_header : ($inphinit_https ? 443 : 80);
}

$inphinit_path = rawurldecode(strtok($_SERVER['REQUEST_URI'], '?'));

if (PHP_SAPI !== 'cli-server') {
    $inphinit_pos = strpos($_SERVER['SCRIPT_NAME'], '/index.php');
    $inphinit_prefix = substr($inphinit_path, 0, $inphinit_pos);
    $inphinit_path = substr($inphinit_path, $inphinit_pos);
} else {
    $inphinit_prefix = '';
}

define('INPHINIT_PATH', $inphinit_path);
define('INPHINIT_URL', $inphinit_proto . '://' . $inphinit_host . ':' . $inphinit_port . $inphinit_prefix);

if ($inphinit_config_development) {
    require 'development.php';
} else {
    $app = new App();
}

require INPHINIT_SYSTEM . '/main.php';
