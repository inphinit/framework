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
    $has_content_disposition = false;
    $has_content_type = false;
    $remove_headers = array();
    $send_file = null;

    foreach (headers_list() as $header) {
        if (stripos($header, 'X-Accel-Redirect:') === 0 || stripos($header, 'X-Sendfile:') === 0) {
            list($name, $send_file) = explode(':', $header, 2);
            $remove_headers[] = $name;
        } elseif (stripos($header, 'Content-Disposition:') === 0) {
            $has_content_disposition = true;
        }
    }

    if ($send_file !== null && headers_sent() === false) {
        foreach ($remove_headers as $header) {
            header_remove($header);
        }

        $send_file = trim($send_file);

        if (File::exists($send_file)) {
            if ($has_content_type === false) {
                header('Content-Type: application/octet-stream');
            }

            File::output($send_file);
        } else {
            Response::status(404);

            if ($has_content_disposition) {
                header_remove('Content-Disposition');
            }

            if (function_exists('ini_get')) {
                $default_charset = ini_get('default_charset');
                $default_mimetype = ini_get('default_mimetype');

                if ($default_mimetype) {
                    $content_type = $default_mimetype;
                } else {
                    $content_type = 'text/html';
                }

                if ($default_charset) {
                    $content_type .= ';' . $default_charset;
                }

                header('Content-Type: ' . $content_type);
                inphinit_sandbox('errors.php', array('code' => 404));
            }
        }
    }
});
