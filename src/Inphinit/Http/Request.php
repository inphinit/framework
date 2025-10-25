<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\Utility\PropertyAccessor;

class Request
{
    private static $headerTokens = array('-', ' ');

    /**
     * Get current HTTP path
     *
     * @return string
     */
    public static function path()
    {
        return rawurldecode(strtok($_SERVER['REQUEST_URI'], '?'));
    }

    /**
     * Checks if the request matches a specific type: HTTPS, XHR, Pjax, prefetch, save-data, GPC, or a standard HTTP method (eg.: GET, POST).
     *
     * @param string $type The type to check (eg.: 'gpc', 'pjax', 'prefetch', 'save', 'secure', 'xhr', 'POST', 'HEAD').
     * @return bool
     */
    public static function is($type)
    {
        switch ($type) {
            case 'gpc':
                return self::header('sec-gpc', '') === '1';

            case 'pjax':
                return self::headerMatches('x-pjax', 'true');

            case 'prefetch':
                return (
                    self::headerMatches('sec-purpose', 'prefetch') ||
                    self::headerMatches('x-purpose', 'preview') ||
                    self::headerMatches('purpose', 'prefetch') ||
                    self::headerMatches('x-moz', 'prefetch')
                );

            case 'save':
                return self::headerMatches('save-data', 'on');

            case 'secure':
                return strpos(INPHINIT_URL, 'https') === 0;

            case 'xhr':
                return self::headerMatches('x-requested-with', 'xmlhttprequest');

            default:
                return strcasecmp($_SERVER['REQUEST_METHOD'], $type) === 0;
        }
    }

    private static function headerMatches($header, $target)
    {
        return strcasecmp(self::header($header, ''), $target) === 0;
    }

    /**
     * Get HTTP header from current request
     *
     * @param string $name
     * @param mixed  $alternative
     * @return mixed
     */
    public static function header($name, $alternative = null)
    {
        $name = 'HTTP_' . strtoupper(str_replace(self::$headerTokens, '_', $name));
        return isset($_SERVER[$name]) ? $_SERVER[$name] : $alternative;
    }

    /**
     * Get querystring - Note: same as $_SERVER['QUERY_STRING'], but with framework adjustments on IIS web server
     *
     * @return string|null
     */
    public static function query()
    {
        return empty($_GET['INPHINIT_REDIRECT']) && isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null;
    }

    /**
     * Get a value from `$_GET`, if `$_GET` is a array multidimensional, you can use dot like path:
     * If `$_GET['foo']` returns this `array('baz' => 'bar' => 1);` use `Request::get('foo.bar.baz');`
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
     * If $_POST['foo'] returns this array('baz' => 'bar' => 1); use Request::post('foo.bar.baz');
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

    private static function data($data, $key, $alternative)
    {
        if (empty($data) || is_array($data) === false) {
            return $alternative;
        } elseif (strpos($key, '.') === false) {
            return isset($data[$key]) ? $data[$key] : $alternative;
        }

        return PropertyAccessor::getValue($key, $data, $alternative);
    }
}
