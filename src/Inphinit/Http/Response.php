<?php
/*
 * Inphinit
 *
 * Copyright (c) 2023 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\App;

class Response
{
    private static $httpCode;
    private static $httpType;
    private static $httpCharset;
    private static $headers = array();
    private static $dispatchedHeaders = false;

    /**
     * Define registered headers to response
     *
     * @return void
     */
    public static function dispatch()
    {
        if (self::$dispatchedHeaders === false) {
            self::$dispatchedHeaders = true;

            foreach (self::$headers as $value) {
                self::putHeader($value[0], $value[1], $value[2]);
            }

            $httpType = self::$httpType;

            if ($httpType || self::$httpCharset) {
                if (!$httpType) $httpType = 'text/html';

                header('Content-Type: ' . $httpType . '; charset=' . self::$httpCharset);
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
     * @param bool $trigger
     * @return int|bool
     */
    public static function status($code = null, $trigger = true)
    {
        if (self::$httpCode === null) {
            self::$httpCode = \UtilsStatusCode();
        }

        if ($code === null || self::$httpCode === $code) {
            return self::$httpCode;
        } elseif (headers_sent() || $code < 100 || $code > 599) {
            return false;
        }

        header('X-PHP-Response-Code: ' . $code, true, $code);

        $lastCode = self::$httpCode;
        self::$httpCode = $code;

        if ($trigger) {
            App::trigger('changestatus', array($code, null));
        }

        return $lastCode;
    }

    /**
     * Register a header and return your index, if `Response::dispatch`
     * was previously executed the header will be set directly and will not be
     * registered
     *
     * @param string $name
     * @param string $value
     * @param bool   $replace
     * @return void
     */
    public static function putHeader($name, $value, $replace = true)
    {
        if (self::$dispatchedHeaders || App::state() > 2) {
            header($name . ': ' . ltrim($value), $replace);
        } else {
            self::$headers[] = array($name, $value, $replace);
        }
    }

    /**
     * Remove registered (or setted) header
     *
     * @param string $name
     * @return void
     */
    public static function removeHeader($name)
    {
        if (self::$dispatchedHeaders || App::state() > 2) {
            header_remove($name);
        } else {
            self::$headers = array_filter(self::$headers, function ($header) use ($name) {
                return strcasecmp($header[0], $name) !== 0;
            });
        }
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
    public static function download($name = null, $contentLength = 0)
    {
        if ($name) {
            $name = '; filename="' . strtr($name, '"', '-') . '"';
        } else {
            $name = '';
        }

        self::putHeader('Content-Transfer-Encoding', 'Binary');
        self::putHeader('Content-Disposition', 'attachment' . $name);

        if ($contentLength > 0) {
            self::putHeader('Content-Length', $contentLength);
        }
    }

    /**
     * Set content-type
     *
     * @param string $mime
     * @return void
     */
    public static function type($type)
    {
        self::$httpType = trim($type);
    }

    /**
     * Set charset in content-type
     *
     * @param string $mime
     * @return void
     */
    public static function charset($charset)
    {
        self::$httpCharset = trim($charset);
    }
}
