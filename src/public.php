<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Filesystem\File;

function inphinit_public_sandbox($sandbox_path)
{
    if (realpath($sandbox_path) === false) {
        http_response_code(404);
    } else {
        include $sandbox_path;
        exit;
    }
}

function inphinit_public_index($path)
{
    $php = $path . 'index.php';

    if (is_file($php)) {
        return $php;
    }

    $html = $path . 'index.html';

    if (is_file($html)) {
        return $html;
    }

    return false;
}

$inphinit_public_source = INPHINIT_ROOT . '/public' . $inphinit_path;

if ($inphinit_path === '/' || strpos($inphinit_path, '/.') !== false) {
    $inphinit_public_source = false;
} elseif (substr($inphinit_path, -1) === '/') {
    $inphinit_public_source = inphinit_public_index($inphinit_public_source);
} elseif (is_file($inphinit_public_source) === false) {
    $inphinit_public_source = false;
}

if ($inphinit_public_source !== false) {
    $inphinit_public_type = null;

    $inphinit_public_suffix = pathinfo($inphinit_public_source, PATHINFO_EXTENSION);

    if ($inphinit_public_suffix) {
        $inphinit_public_suffix = strtolower($inphinit_public_suffix);

        if ($inphinit_public_suffix === 'php') {
            inphinit_public_sandbox($inphinit_public_source);
        }

        $inphinit_public_media_types = require INPHINIT_SYSTEM . '/boot/media_types.php';

        foreach ($inphinit_public_media_types as $mime => $suffixes) {
            if (in_array($inphinit_public_suffix, $suffixes, true)) {
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
