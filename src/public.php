<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Filesystem\File;

function inphinit_sandbox_public($sandbox_path)
{
    $sandbox_path = INPHINIT_ROOT . '/public/' . $sandbox_path;

    if (realpath($sandbox_path) === false) {
        http_response_code(404);
    } else {
        include $sandbox_path;
        exit;
    }
}

$inphinit_public_source = INPHINIT_ROOT . '/public' . $inphinit_path;

if ($inphinit_path !== '/' && strpos($inphinit_path, '/.') === false && is_file($inphinit_public_source)) {
    $inphinit_public_type = null;

    $inphinit_public_suffix = pathinfo($inphinit_path, PATHINFO_EXTENSION);

    if ($inphinit_public_suffix) {
        $inphinit_public_suffix = strtolower($inphinit_public_suffix);

        if (strcasecmp($inphinit_public_suffix, 'php') === 0) {
            inphinit_sandbox_public($inphinit_path);
        }

        $inphinit_public_media_types = require INPHINIT_SYSTEM . '/boot/media_types.php';

        foreach ($inphinit_public_media_types as $mime => $suffixes) {
            if (in_array($inphinit_public_suffix, $suffixes)) {
                $inphinit_public_type = $mime;
                break;
            }
        }
    }

    if ($inphinit_public_type === null) {
        $inphinit_public_type = 'text/html';
    }

    if (is_readable($inphinit_public_source)) {
        header('Content-Type: ' . $inphinit_public_type, true);
        File::output($inphinit_public_source);
        exit;
    }

    http_response_code(403);
}
