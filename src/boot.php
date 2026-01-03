<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
use Inphinit\Event;

header_remove('X-Powered-By');

define('INPHINIT_MAINTENANCE', INPHINIT_SYSTEM . '/storage/.maintenance');

require __DIR__ . '/Inphinit/App.php';

/**
 * Checks whether the file exists using a case-sensitive comparison, regardless of the operating system.
 * Used internally by the framework for components such as the autoloader, controllers, and others.
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
 * Function used by `set_error_handler` and triggered by `Event::trigger('error')`
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

$inphinit_optimized_env = INPHINIT_SYSTEM . '/boot/env.php';

if (App::config('skip_env_file') !== '1') {
    if (PHP_SAPI !== 'cli' && is_file($inphinit_optimized_env)) {
        require $inphinit_optimized_env;
    } else {
        require __DIR__ . '/env_vars.php';
    }
}

$inphinit_config_development = App::config('environment') === 'development';

if (App::config('composer_autoload') === '1') {
    require_once INPHINIT_SYSTEM . '/vendor/autoload.php';
} else {
    /*
     * Using `set_include_path()` optimizes how Controllers, Models, and other framework classes are loaded.
     * Note: For production mode only; in development mode, additional checks are performed to detect errors.
     */
    if (!$inphinit_config_development) {
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

    // Load autoload.files (https://getcomposer.org/doc/04-schema.md#files)
    $inphinit_boot_files = INPHINIT_SYSTEM . '/boot/files.php';

    if (is_file($inphinit_boot_files)) {
        require_once __DIR__ . '/require_files.php';
    }
}

if (PHP_SAPI === 'cli') {
    return null;
}

$inphinit_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

$inphinit_proto = App::config('forwarded_proto');
$inphinit_host = App::config('forwarded_host');
$inphinit_port = App::config('forwarded_port');

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
    require __DIR__ . '/development.php';
} else {
    $app = new App();
}

require INPHINIT_SYSTEM . '/main.php';
