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
     * Note: While PHP discards headers containing line breaks and issues a warning, line breaks
     *       aren't expected. This function will now throw an exception if it encounters one,
     *       preventing silent issues.
     *
     * @param string $name
     * @param string $value
     * @param bool   $replace
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function header($name, $value, $replace = true)
    {
        if ($value === null) {
            self::checkHeaderContent($name);

            header_remove($name);
        } else {
            self::checkHeaderContent($value);

            header($name . ': ' . $value, $replace);
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
     * Set HTTP cache or no-cache
     *
     * @param int $seconds  Set cache in seconds. If $seconds is less than 1, caching is disabled.
     * @param int $modified Optional. Last modified timestamp. Defaults to the current time.
     * @return void
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
     * @param string $name   File name for download (eg.: "report.pdf")
     * @param int    $length Optional. File size in bytes
     * @return void
     */
    public static function download($name, $length = 0)
    {
        if (is_string($name) === false || $name === '' || basename($name) !== $name) {
            throw new Exception('Invalid name');
        }

        if (preg_match('#^[\x00-\x7F]+$#', $name)) {
            // Only ASCII
            $filename = '; filename="' . $name . '"';
        } else {
            // Only UTF-8 + ASCII fallback
            $filename = '; filename="' . Strings::toAscii($name) . '"';
            $filename .= '; filename*=UTF-8\'\'' . rawurlencode($name);
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
