<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\App;
use Inphinit\Event;
use Inphinit\Exception;
use Inphinit\Strings;

class Response
{
    /**
     * Get or set status code and return previous status code.
     * Note: if the status has changed the Event::on('changestatus') event will be trigged
     *
     * @param int $code
     * @return int
     */
    public static function status($code = 0)
    {
        $previous = http_response_code();

        if ($code > 0 && $previous !== $code && class_exists('\\Inphinit\\Event', false)) {
            http_response_code($code);
            Event::trigger('changestatus', array($code));
        }

        return $previous;
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
    public static function type($type, $charset = null)
    {
        if ($type === null) {
            header_remove('Content-Type');
        } else {
            if ($charset && ($charset = trim($charset))) {
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

        if ($expires >= 1) {
            header('Cache-Control: public, max-age=' . $expires);
            $date = gmdate('D, d M Y H:i:s', $time + $expires);
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            $date = gmdate('D, d M Y H:i:s');
        }

        header('Expires: ' . $date . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified > 0 ? $modified : $time) . ' GMT');
    }

    /**
     * Force download current response
     *
     * @param string $name   File name for download (eg.: "report.pdf")
     * @param int    $length Optional. File size in bytes
     * @return void
     */
    public static function download($name, $length = 0)
    {
        if (basename($name) !== $name) {
            throw new Exception('Invalid name: ' . $name);
        }

        if (preg_match('#^[\x00-\x7F]+$#', $name)) {
            // Only ASCII
            $filename = '; filename="' . $name . '"';
        } elseif (preg_match('#^.+$#u', $name)) {
            // Only UTF-8 + ASCII fallback
            $filename = '; filename="' . Strings::toAscii($name) . '"';
            $filename .= '; filename*=UTF-8\'\'' . rawurlencode($name);
        } else {
            // If the string is empty, or has invalid characters or line breaks
            throw new Exception('Empty string or invalid characters in name: ' . $name);
        }

        header('Content-Transfer-Encoding: Binary');
        header('Content-Disposition: attachment' . $filename);

        if ($length > 0) {
            header('Content-Length: ' . $length);
        }
    }
}
