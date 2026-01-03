<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Event;
use Inphinit\Filesystem\File;
use Inphinit\Http\Response;

Event::on('done', function () {
    $send_file = null;
    $remove_headers = array();
    $fallback_content_type = true;

    foreach (headers_list() as $header) {
        if (stripos($header, 'X-Accel-Redirect:') === 0 || stripos($header, 'X-Sendfile:') === 0) {
            list($name, $send_file) = explode(':', $header, 2);
            $remove_headers[] = $name;
        } elseif (stripos($header, 'Content-Type:') === 0) {
            $fallback_content_type = false;
        }
    }

    if ($send_file && !headers_sent()) {
        foreach ($remove_headers as $header) {
            header_remove($header);
        }

        $send_file = trim($send_file);

        if (File::exists($send_file)) {
            if ($fallback_content_type) {
                header('Content-Type: application/octet-stream');
            }

            File::output($send_file);
        } else {
            Response::status(404);
        }
    }
});
