<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

error_reporting(E_ALL);

set_error_handler('inphinit_error', E_ALL);

$app = new Inphinit\Debugging\App();
$debug = new Inphinit\Debugging\Debug();

require INPHINIT_SYSTEM . '/dev.php';
