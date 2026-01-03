<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

/**
 * Sandbox include autoload files
 *
 * @param string $sandbox_path
 * @return mixed
 */
function inphinit_sandbox_file($sandbox_path)
{
    return include $sandbox_path;
}

inphinit_sandbox_file($inphinit_boot_files);
