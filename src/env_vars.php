<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Experimental\Environment\EnvFile;

require_once __DIR__ . '/Inphinit/Diagnostics/Inspector.php';
require_once __DIR__ . '/Inphinit/Exception.php';
require_once __DIR__ . '/Experimental/Environment/EnvException.php';
require_once __DIR__ . '/Experimental/Environment/EnvFile.php';
require_once __DIR__ . '/Experimental/Environment/Parser.php';

$env = new EnvFile(INPHINIT_ROOT . '/.env');
$env->fill();
