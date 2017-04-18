<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Request
{
    private static $reqHeaders;

    /**
     * Get current HTTP path or route path
     *
     * @param bool $info
     * @return string|bool
     */
    public static function path($info = false)
    {
        if ($info === true) {
            return \UtilsPath();
        }

        if (isset($_SERVER['REQUEST_URI'])) {
            return preg_replace('#\?(.*)$#', '', $_SERVER['REQUEST_URI']);
        }

        return false;
    }

    /**
     * Check if is a specific HTTP method, HTTPS, and xmlhttprequest (Depends on how an ajax call was made)
     *
     * @param string $check
     * @return bool
     */
    public static function is($check)
    {
        switch ($check) {
            case 'secure':
                return empty($_SERVER['HTTPS']) === false && strcasecmp($_SERVER['HTTPS'], 'on') === 0;

            case 'xhr':
                return strcasecmp(self::header('X-Requested-With'), 'xmlhttprequest') === 0;

            case 'pjax':
                return strcasecmp(self::header('X-Pjax'), 'true') === 0;
        }

        return isset($_SERVER['REQUEST_METHOD']) && strcasecmp($_SERVER['REQUEST_METHOD'], $check) === 0;
    }

    /**
     * Get http headers from current request
     *
     * @param string $name
     * @return string|array|bool
     */
    public static function header($name = null)
    {
        if (is_string($name)) {
            $name = 'HTTP_' . strtoupper(strtr($name, '-', '_'));
            return isset($_SERVER[$name]) ? $_SERVER[$name] : false;
        }

        if (self::$reqHeaders !== null) {
            return self::$reqHeaders;
        }

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $current = Helper::capitalize(substr($key, 5), '_', '-');
                $headers[$current] = $value;
            }
        }

        self::$reqHeaders = $headers;

        return $headers;
    }

    /**
     * Get querystring, this method is useful for anyone who uses IIS.
     *
     * @return string|bool
     */
    public static function query()
    {
        if (empty($_GET['RESERVED_IISREDIRECT']) === false) {
            return false;
        }

        return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : false;
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
    public static function get($key, $alternative = false)
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
    public static function post($key, $alternative = false)
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
    public static function cookie($key, $alternative = false)
    {
        return self::data($_COOKIE, $key, $alternative);
    }

    /**
     * Get a value from `$_FILES` (support path using dots)
     *
     * @param string $key
     * @return mixed
     */
    public static function file($key)
    {
        $pos = strpos($key, '.');
        $firstKey = $key;
        $restKey  = null;

        if ($pos > 0) {
            $firstKey = substr($key, 0, $pos);
            $restKey  = substr($key, $pos + 1);
        }

        if (isset($_FILES[$firstKey]['name']) === false) {
            return false;
        }

        if ($restKey === null) {
            return $_FILES[$firstKey];
        }

        $tmpName = Helper::arrayPath($restKey, $_FILES[$firstKey]['tmp_name']);

        if ($tmpName === false) {
            return false;
        }

        return array(
            'tmp_name' => $tmpName,
            'name'     => Helper::arrayPath($restKey, $_FILES[$firstKey]['name']),
            'type'     => Helper::arrayPath($restKey, $_FILES[$firstKey]['type']),
            'error'    => Helper::arrayPath($restKey, $_FILES[$firstKey]['error']),
            'size'     => Helper::arrayPath($restKey, $_FILES[$firstKey]['size'])
        );
    }

    /**
     * Get a value input handler
     *
     * @param bool $binary
     * @return resource|bool
     */
    public static function raw($binary = true)
    {
        if (is_readable('php://input')) {
            return false;
        }

        $mode = $binary === true ? 'rb' : 'r';

        if (PHP_VERSION_ID >= 50600) {
            return fopen('php://input', $mode);
        }

        $tmp = Storage::temp();

        return copy('php://input', $tmp) ? fopen($tmp, $mode) : false;
    }

    private static function data($data, $key, $alternative = false)
    {
        if (empty($data)) {
            return $alternative;
        }

        if (strpos($key, '.') === false) {
            return empty($data[$key]) ? $data[$key] : $alternative;
        }

        $data = Helper::arrayPath($key, $data);
        return $data === false ? $alternative : $data;
    }
}
