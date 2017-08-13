<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Response
{
    private static $httpCode;
    private static $headers = array();
    private static $dispatchedHeaders = false;

    /**
     * Define registered headers to response
     *
     * @return void
     */
    public static function dispatch()
    {
        if (empty(self::$headers) === false) {
            self::$dispatchedHeaders = true;

            foreach (self::$headers as $value) {
                self::putHeader($value[0], $value[1], $value[2]);
            }
        }
    }

    /**
     * Get registered headers
     *
     * @return array
     */
    public static function getHeaders()
    {
        return self::$headers;
    }

    /**
     * Get or set status code and return last status code
     *
     * @param int  $code
     * @param bool $preventTrigger
     * @return int|bool
     */
    public static function status($code = null, $preventTrigger = false)
    {
        if (self::$httpCode === null) {
            self::$httpCode = \UtilsStatusCode();
        }

        if (self::$httpCode === $code || headers_sent() || $code < 100 || $code > 599) {
            return false;
        }

        header('X-PHP-Response-Code: ' . $code, true, $code);

        $lastCode = self::$httpCode;
        self::$httpCode = $code;

        if (false === $preventTrigger) {
            App::trigger('changestatus', array($code, null));
        }

        return $lastCode;
    }

    /**
     * Register a header and return your index, if `Response::dispatch`
     * was previously executed the header will be set directly and will not be
     * registered
     *
     * @param string $header
     * @param bool   $replace
     * @return void
     */
    public static function putHeader($name, $value, $replace = true)
    {
        if (self::$dispatchedHeaders || App::isReady()) {
            header($name . ': ' . ltrim($value), $replace);
        } else {
            self::$headers[] = array($name, $value, $replace);
        }
    }

    /**
     * Remove registered header
     *
     * @param string $name
     * @return void
     */
    public static function removeHeader($name)
    {
        self::$headers = array_filter(self::$headers, function ($header) use ($name) {
            return strcasecmp($header[0], $name) !== 0;
        });
    }

    /**
     * Set header to cache page (or no-cache)
     *
     * @param int $seconds
     * @param int $modified
     * @return void
     */
    public static function cache($seconds, $modified = 0)
    {
        if ($seconds < 1) {
            self::putHeader('Expires', gmdate('D, d M Y H:i:s') . ' GMT');
            self::putHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            self::putHeader('Cache-Control', 'post-check=0, pre-check=0', false);
            self::putHeader('Pragma', 'no-cache');
        } else {
            self::putHeader('Expires', gmdate('D, d M Y H:i:s', REQUEST_TIME + $seconds) . ' GMT');
            self::putHeader('Cache-Control', 'public, max-age=' . $seconds);
            self::putHeader('Pragma', 'max-age=' . $seconds);
        }

        self::putHeader('Last-Modified', gmdate('D, d M Y H:i:s', $modified > 0 ? $modified : REQUEST_TIME) . ' GMT');
    }

    /**
     * Force download current page
     *
     * @param string $name
     * @param int    $contentLength
     * @return void
     */
    public static function download($name, $contentLength = 0)
    {
        if (is_string($name)) {
            self::putHeader('Content-Transfer-Encoding', 'Binary');
            self::putHeader('Content-Disposition', 'attachment; filename="' . strtr($name, '"', '-') . '"');
        }

        if ($contentLength > 0) {
            self::putHeader('Content-Length', $contentLength);
        }
    }

    /**
     * Set mime-type
     *
     * @param string $mime
     * @return void
     */
    public static function type($mime)
    {
        self::putHeader('Content-Type', $mime);
    }
}
