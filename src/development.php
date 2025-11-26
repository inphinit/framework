<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

error_reporting(E_ALL);

$app = new Inphinit\Diagnostics\App();
$debug = new Inphinit\Diagnostics\Debug();

require INPHINIT_SYSTEM . '/dev.php';
require __DIR__ . '/sendfile.php';

// Restore namespace prefix to avoid side effects in main.php
$app->setNamespace('Controllers');
