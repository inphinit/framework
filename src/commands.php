<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
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
    if (unlink(INPHINIT_SYSTEM . '/boot/env.php')) {
        echo 'Successfully disabling `.env` optimization';
    } else {
        echo 'Failed to disable `.env` optimization';
    }
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

    $root = INPHINIT_ROOT;
    $router = escapeshellarg($root . '/index.php');
    $log = escapeshellarg(INPHINIT_SYSTEM . '/storage/logs/errors.log');
    $public = escapeshellarg($root . '/public');

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
