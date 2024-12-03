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
     * Get or set status code and return last status code. Note: if the status has changed the Event::on('changestatus') event will be trigged
     *
     * @param int $code
     * @return bool|int
     */
    public static function status($code)
    {
        $lastCode = http_response_code($code);

        if ($lastCode && $lastCode !== $code && class_exists('\\Inphinit\\Event', false)) {
            Event::trigger('changestatus', array($code));
        }

        return $lastCode;
    }

    /**
     * Shortcut for set header
     *
     * @param string $header
     * @param string $value
     * @param bool   $replace
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
     * Set Content-Type header or remove previously headers
     *
     * @param string|null $type
     * @param string|null $charset
     * @return void
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
     * Set HTTP cache
     *
     * @param int $expires
     * @param int $modified
     * @return void
     */
    public static function cache($expires, $modified = 0)
    {
        $time = time();

        if ($expires < 1) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');

            $date = gmdate('D, d M Y H:i:s');
        } else {
            header('Cache-Control: public, max-age=' . $expires);
            header('Pragma: max-age=' . $expires);

            $date = gmdate('D, d M Y H:i:s', $time + $expires);
        }

        header('Expires: ' . $date . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified > 0 ? $modified : $time) . ' GMT');
    }

    /**
     * Force download current response
     *
     * @param string $name
     * @param int    $length
     * @return void
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
