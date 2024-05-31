<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\App;
use Inphinit\Event;

class Response
{
    /**
     * Get or set status code and return last status code. Note: if set status work, Event::on('changestatus') is trigged
     *
     * @param int  $code
     * @param bool $trigger
     * @return int|bool
     */
    public static function status($code)
    {
        $lastCode = http_response_code($code);

        if ($lastCode && $lastCode !== $code) {
            App::trigger('changestatus', array($code));
        }

        return $lastCode;
    }

    /**
     * Short
     *
     * @param string      $code
     * @param string|null $trigger
     * @param bool        $replace
     * @return void
     */
    public static function header($header, $value, $replace = true)
    {
        if ($value === null) {
            header_remove($header);
        } else {
            header($header . ': ' . $value, $replace);
        }
    }

    /**
     * Get or set status code and return last status code
     *
     * @param string|null $name
     * @param string|null $charset
     * @return bool
     */
    public static function content($type, $charset = null)
    {
        if ($type === null) {
            header_remove('Content-Type');
        } else {
            if ($charset) {
                $type .= ';charset=' . $charset;
            }

            header('Content-Type: ' . $type);
        }
    }

    /**
     * Set header to cache page Response::cache($seconds, $modified = 0);
     *
     * @param int $expires
     * @param int $modified
     * @return bool
     */
    public static function cache($expires, $modified = 0)
    {
        $time = time();

        if ($expires < 1) {
            header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
        } else {
            header('Expires: ' . gmdate('D, d M Y H:i:s', $time + $expires) . ' GMT');
            header('Cache-Control: public, max-age=' . $expires);
            header('Pragma: max-age=' . $expires);
        }

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified > 0 ? $modified : $time) . ' GMT');
    }

    /**
     * Force download current page
     *
     * @param string $name
     * @param int    $length
     * @return bool
     */
    public static function download($name, $length = 0)
    {
        $name = '; filename="' . rawurlencode($name) . '"';

        header('Content-Transfer-Encoding: Binary');
        header('Content-Disposition: attachment' . $name);

        if ($length > 0) {
            header('Content-Length: ' . $length);
        }
    }
}
