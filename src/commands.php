<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
use Inphinit\Maintenance;
use Inphinit\Experimental\Cli\Command;
use Inphinit\Experimental\Cli\Console;

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/env_vars.php';

$console = new Console();

$console->action('env:boot', function (Command $command, array $params, array $residual) use ($env) {
    if (isset($params['override'])) {
        $env->setOverride(true);
    }

    if ($env->storeAsVars(INPHINIT_SYSTEM . '/boot/env.php')) {
        echo 'Success in optimizing the `.env`';
    } else {
        echo 'Failed in optimizing the `.env`';
    }
})->setOption('override', 'o', Command::ARG_NO_VALUE, null, 'Define override mode');

$console->action('env:source', function (Command $command, array $params, array $residual) use ($env) {
    $envFile = INPHINIT_SYSTEM . '/boot/env.php';

    if (is_file($envFile) === false) {
        echo 'Optimization of the `.env` file is already disabled';
    } elseif (unlink($envFile)) {
        echo 'Successfully disabling `.env` optimization';
    } else {
        echo 'Failed to disable `.env` optimization';
    }
});

$console->action('app:down', function (Command $command, array $params, array $residual) {
    Maintenance::down();
});

$console->action('app:up', function (Command $command, array $params, array $residual) {
    Maintenance::up();
});

$serve = $console->action('serve', function (Command $command, array $params, array $residual) {
    $host = isset($params['host']) ? $params['host'] : App::config('built_in_host');
    $port = isset($params['port']) ? $params['port'] : App::config('built_in_port');
    $vars = isset($params['vars']) ? $params['vars'] : 'EGPCS';

    if (empty($host)) {
        echo 'Empty host';
        exit(-1);
    }

    if (empty($port)) {
        echo 'Empty port';
        exit(-1);
    }

    if (empty($vars)) {
        echo 'Empty vars';
        exit(-1);
    }

    $log = escapeshellarg(INPHINIT_SYSTEM . '/storage/logs/errors.log');
    $root = INPHINIT_ROOT;
    $public = escapeshellarg($root . '/public');
    $router = escapeshellarg($root . '/index.php');

    passthru("php -d variables_order={$vars} -d error_log={$log} -S {$host}:{$port} -t {$public} {$router}", $code);

    exit($code);
});

$serve->setOption('host', 'h', Command::ARG_OPTIONAL, null, 'Define server address');
$serve->setOption('port', 'p', Command::ARG_OPTIONAL, null, 'Define server port');
$serve->setOption('vars', 'v', Command::ARG_OPTIONAL, null, 'Define variables order');

$console->action('packages:optimize', function (Command $command, array $params, array $residual) {
    require_once INPHINIT_SYSTEM . '/boot/importpackages.php';
});

require INPHINIT_SYSTEM . '/console.php';
