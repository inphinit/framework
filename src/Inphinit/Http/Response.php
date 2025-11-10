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
     * Note: If the status has changed, the `Event::on('changestatus')` event will be triggered
     *
     * @param int $code
     * @return int
     */
    public static function status($code = 0)
    {
        $previous = http_response_code($code);

        if ($code !== 0 && $previous !== $code && class_exists('\\Inphinit\\Event', false)) {
            Event::trigger('changestatus', array($code));
        }

        return $previous;
    }

    /**
     * Shortcut for set header
     * Note: While PHP internally discards headers containing line breaks and issues a warning,
     *       such content is explicitly forbidden by this function. An exception will now
     *       be thrown if line breaks are detected, preventing both silent issues and potential
     *       misinterpretations of header behavior.
     *
     * @param string      $name
     * @param string|null $value
     * @param bool        $replace
     * @throws \Inphinit\Exception
     */
    public static function header($name, $value, $replace = true)
    {
        self::checkHeaderContent($name);

        if ($value === null) {
            header_remove($name);
        } else {
            self::checkHeaderContent($value);

            header($name . ': ' . $value, $replace);
        }
    }

    /**
     * Set Content-Type header or remove previously headers
     *
     * @param string|null $value
     * @param string|null $charset
     */
    public static function type($value, $charset = null)
    {
        if ($value === null) {
            header_remove('Content-Type');
        } else {
            self::checkHeaderContent($value);

            if ($charset && ($charset = trim($charset))) {
                self::checkHeaderContent($charset);

                $value .= '; charset=' . $charset;
            }

            header('Content-Type: ' . $value);
        }
    }

    /**
     * Set HTTP cache or no-cache
     *
     * @param int $seconds  Set cache in seconds. If $seconds is less than 1, caching is disabled.
     * @param int $modified Optional. Last modified timestamp. Defaults to the current time.
     */
    public static function cache($seconds, $modified = 0)
    {
        $time = time();

        if ($seconds > 0) {
            header('Cache-Control: public, max-age=' . $seconds);
            $date = gmdate('D, d M Y H:i:s', $time + $seconds);
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            $date = gmdate('D, d M Y H:i:s');
        }

        header('Expires: ' . $date . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified > 0 ? $modified : $time) . ' GMT');
    }

    /**
     * Force download current response
     *
     * @param string $name   File name for download (e.g., "report.pdf")
     * @param int    $length Optional. File size in bytes
     */
    public static function download($name, $length = 0)
    {
        if (is_string($name) === false || $name === '' || basename($name) !== $name) {
            throw new Exception('Invalid name: ' . $name);
        }

        if (preg_match('#^[\x00-\x7F]+$#', $name)) {
            // Only ASCII
            $filename = '; filename="' . $name . '"';
        } else {
            // Only UTF-8 + ASCII fallback
            $filename = '; filename="' . Strings::toAscii($name) . '"';

            if (preg_match('//u', $name)) {
                $filename .= '; filename*=UTF-8\'\'' . rawurlencode($name);
            }
        }

        header('Content-Transfer-Encoding: Binary');
        header('Content-Disposition: attachment' . $filename);

        if ($length > 0) {
            header('Content-Length: ' . $length);
        }
    }

    private static function checkHeaderContent($data)
    {
        if (preg_match('#[\r\n]#', $data)) {
            throw new Exception("Header may not contain more than a single header, new line detected", 0, 3);
        }
    }
}
