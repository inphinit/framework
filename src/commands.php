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

require_once 'boot.php';
require_once 'env_vars.php';

$console = new Inphinit\Experimental\Cli\Console();

$console->action('env:optimize', function ($command, $params, $residual) use ($env) {
    if ($env->storeAsVars(INPHINIT_SYSTEM . '/boot/env.php')) {
        return 'Success in optimizing the `.env`';
    }

    return 'Failed in optimizing the `.env`';
});

$console->action('env:source', function ($command, $params, $residual) use ($env) {
    if (unlink(INPHINIT_SYSTEM . '/boot/env.php')) {
        return 'Successfully disabling `.env` optimization';
    }

    return 'Failed to disable `.env` optimization';
});

$serve = $console->action('serve', function ($command, $params, $residual) {
    $host = isset($params['host']) ? $params['host'] : App::config('built_in_host');
    $port = isset($params['port']) ? $params['port'] : App::config('built_in_port');
    $vars = isset($params['vars']) ? $params['vars'] : 'EGPCS';
    $root = INPHINIT_ROOT;
    $router = escapeshellarg($root . '/index.php');

    $root = escapeshellarg($root);

    if (empty($host)) {
        echo 'Empty host';
        exit(-1);
    }

    if (empty($port)) {
        echo 'Empty port';
        exit(-1);
    }

    passthru("php -d variables_order={$vars} -S {$host}:{$port} -t {$root} {$router}", $code);

    exit($code);
});

$serve->setOption('host', null, Command::OPT_OPTIONAL, null, 'Define server address');
$serve->setOption('port', null, Command::OPT_OPTIONAL, null, 'Define server port');
$serve->setOption('vars', null, Command::OPT_OPTIONAL, null, 'Define variables order');

$console->action('packages:optimize', function ($command, $params, $residual) {
    require_once INPHINIT_SYSTEM . '/boot/importpackages.php';
});

require INPHINIT_SYSTEM . '/console.php';
