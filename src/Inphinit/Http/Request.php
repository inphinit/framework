<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\Utility\Others;

class Request
{
    private static $reqHeaders;
    private static $reqHeadersLower;
    private static $headerTokens = array('-', ' ');

    /**
     * Get current HTTP path
     *
     * @return string
     */
    public static function path()
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * Check is request is: HTTPS, XMLHttpRequest, Pjax, prefetch, save-data or HTTP methods
     *
     * @param string $check
     * @return bool
     */
    public static function is($check)
    {
        switch ($check) {
            case 'pjax':
                return strcasecmp(self::header('x-pjax', ''), 'true') === 0;

            case 'prefetch':
                return (
                    strcasecmp(self::header('sec-purpose', ''), 'prefetch') === 0 ||
                    strcasecmp(self::header('x-purpose', ''), 'preview') === 0 ||
                    strcasecmp(self::header('purpose', ''), 'prefetch') === 0 ||
                    strcasecmp(self::header('x-moz', ''), 'prefetch') === 0
                );

            case 'save':
                return strcasecmp(self::header('save-data', ''), 'on') === 0;

            case 'secure':
                return strpos(INPHINIT_URL, 'https') === 0;

            case 'xhr':
                return strcasecmp(self::header('x-requested-with', ''), 'xmlhttprequest') === 0;

            default:
                return strcasecmp($_SERVER['REQUEST_METHOD'], $check) === 0;
        }
    }

    /**
     * Get HTTP header from current request
     *
     * @param string $name
     * @param mixed  $alternative
     * @return string|null
     */
    public static function header($name, $alternative = null)
    {
        $name = 'HTTP_' . strtoupper(str_replace(self::$headerTokens, '_', $name));
        return isset($_SERVER[$name]) ? $_SERVER[$name] : $alternative;
    }

    /**
     * Get querystring, this method is useful for anyone who uses IIS.
     *
     * @return string|null
     */
    public static function query()
    {
        return empty($_GET['INPHINIT_REDIRECT']) && isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
    }

    /**
     * Get a value from `$_GET`, if `$_GET` is a array multidimensional, you can use dot like path:
     * If `$_GET['foo']` returns this `array( 'baz' => 'bar' => 1);` use `Request::get('foo.bar.baz');`
     * for return `1`
     *
     * @param string $key
     * @param mixed  $alternative
     * @return mixed
     */
    public static function get($key, $alternative = null)
    {
        return self::data($_GET, $key, $alternative);
    }

    /**
     * Get a value from $_POST, if $_POST is a array multidimensional, you can use dot like path:
     * If $_POST['foo'] returns this array( 'baz' => 'bar' => 1); use Request::post('foo.bar.baz');
     *
     * @param string $key
     * @param mixed  $alternative
     * @return mixed
     */
    public static function post($key, $alternative = null)
    {
        return self::data($_POST, $key, $alternative);
    }

    /**
     * Get a value from `$_COOKIE` (support path using dots)
     *
     * @param string $key
     * @param mixed  $alternative
     * @return mixed
     */
    public static function cookie($key, $alternative = null)
    {
        return self::data($_COOKIE, $key, $alternative);
    }

    private static function data(&$data, $key, $alternative)
    {
        if (empty($data)) {
            return $alternative;
        } elseif (strpos($key, '.') === false) {
            return isset($data[$key]) ? $data[$key] : $alternative;
        }

        return Others::extract($key, $data, $alternative);
    }
}
