<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

error_reporting(E_ALL|E_STRICT);

set_error_handler('inphinit_error', error_reporting());

$app = new Inphinit\Debugging\DebugApp($app);

require INPHINIT_SYSTEM . '/dev.php';
