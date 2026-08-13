<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
use Inphinit\Experimental\Cli\Command;
use Inphinit\Experimental\Cli\Console;

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/env_vars.php';

$console = new Console();

$console->action('app:down', function (Command $command, array $params, array $residual) {
    if (App::down()) {
        echo 'Maintenance mode is now active.';
    } else {
        echo 'Unable to activate maintenance mode.';
        return 1;
    }
});

$console->action('app:up', function (Command $command, array $params, array $residual) {
    if (App::up()) {
        echo 'The application is active, and maintenance mode has been disabled.';
    } else {
        echo 'Unable to deactivate maintenance mode.';
        return 1;
    }
});

$console->action('env:boot', function (Command $command, array $params, array $residual) use ($env) {
    if (isset($params['override'])) {
        $env->setOverride(true);
    }

    if ($env->storeAsVars(INPHINIT_SYSTEM . '/boot/env.php')) {
        echo 'Optimized `.env` with caching on boot.';
    } else {
        echo 'Unable to optimize `.env`.';
        return 1;
    }
})->setOption('override', 'o', Command::ARG_NO_VALUE, null, 'Define override mode');

$console->action('env:source', function (Command $command, array $params, array $residual) use ($env) {
    $env_file = INPHINIT_SYSTEM . '/boot/env.php';

    if (is_file($env_file) === false) {
        echo 'Optimization of the `.env` is already disabled';
    } elseif (unlink($env_file)) {
        echo 'Disabled `.env` optimization at boot.';
    } else {
        echo 'Unable to disable `.env` optimization.';
        return 1;
    }
});

$console->action('pkg:up', function (Command $command, array $params, array $residual) {
    require_once INPHINIT_SYSTEM . '/boot/importpackages.php';
});

$serve = $console->action('serve', function (Command $command, array $params, array $residual) {
    $host = isset($params['host']) ? $params['host'] : App::config('built_in_host');
    $port = isset($params['port']) ? $params['port'] : App::config('built_in_port');
    $vars = isset($params['vars']) ? $params['vars'] : 'EGPCS';

    if (empty($host)) {
        echo 'Empty host';
        return 1;
    }

    if (empty($port)) {
        echo 'Empty port';
        return 1;
    }

    if (empty($vars)) {
        echo 'Empty vars';
        return 1;
    }

    $log = escapeshellarg(INPHINIT_SYSTEM . '/storage/logs/errors.log');
    $server = escapeshellarg($host . ':' . $port);
    $public = escapeshellarg(INPHINIT_ROOT . '/public');
    $router = escapeshellarg(INPHINIT_ROOT . '/index.php');

    // passthru("php -d variables_order={$vars} -d error_log={$log} -S {$server} -t {$public} {$router}", $code);

    $execute = "php -d variables_order={$vars} -d error_log={$log} -S {$server} -t {$public} {$router}";

    $descriptor_spec = array(STDIN, STDOUT, STDERR);

    $handle = proc_open($execute, $descriptor_spec, $pipes);

    do {
        $status = proc_get_status($handle);
        $code = $status['exitcode'];
    } while ($status['running']);

    proc_close($handle);

    return $code;
});

$serve->setOption('host', 'h', Command::ARG_OPTIONAL, null, 'Define server address');
$serve->setOption('port', 'p', Command::ARG_OPTIONAL, null, 'Define server port');
$serve->setOption('vars', 'v', Command::ARG_OPTIONAL, null, 'Define variables order');

// system/console.php
require INPHINIT_SYSTEM . '/console.php';
