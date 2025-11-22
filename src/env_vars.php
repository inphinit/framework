<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Experimental\Environment\EnvFile;

require __DIR__ . '/Experimental/Environment/EnvFile.php';
require __DIR__ . '/Experimental/Environment/Parser.php';
require __DIR__ . '/Inphinit/Debugging/Inspector.php';
require __DIR__ . '/Inphinit/Exception.php';

$env = new EnvFile(INPHINIT_ROOT . '/.env');
$env->fill();
