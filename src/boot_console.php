<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Experimental\Cli\BuiltInCommands;
use Inphinit\Experimental\Cli\Console;
use Inphinit\Experimental\Scheduling\Scheduler;

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/env_vars.php';

/** @var Inphinit\Experimental\Environment\EnvFile $env */

$console = new Console();
$scheduler = new Scheduler();

$scheduler->setBackgroundTaskDispatcher(escapeshellarg(INPHINIT_ROOT . '/run') . ' schedule:run --task %s');
$scheduler->setStorage(INPHINIT_SYSTEM . '/storage/schedule');

$bulti_in = new BuiltInCommands($console, $env, $scheduler);
$bulti_in->register();

// system/console.php
require INPHINIT_SYSTEM . '/console.php';
