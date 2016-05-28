<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Request
{
    private static $reqHeaders = array();

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

    public static function is($check)
    {
        if (empty($_SERVER['REQUEST_METHOD'])) {
            return false;
        }

        switch ($check)
        {
            case 'secure':
                return empty($_SERVER['HTTPS']) === false && strcasecmp($_SERVER['HTTPS'], 'on') === 0;

            case 'xhr':
                return strcasecmp(self::header('X-Requested-With'), 'xmlhttprequest') === 0;

            case 'pjax':
                return strcasecmp(self::header('X-Pjax'), 'true') === 0;
        }

        return strcasecmp($_SERVER['REQUEST_METHOD'], $check) === 0;
    }

    public static function header($name = null)
    {
        if ($name !== null && is_string($name) === false) {
            return false;
        }

        $all = self::$reqHeaders;

        if (empty($all)) {
            $server = $_SERVER;

            foreach ($server as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $current = Helper::camelCase(substr($key, 5), '_', '-');
                    $all[$current] = $value;
                }
            }

            $server = null;
            self::$reqHeaders = $all;
        }

        if ($name !== null) {
            $name = Helper::camelCase($name, '-', '-');
            return  isset($all[$name]) ? $all[$name] : false;
        }

        return $all;
    }

    public static function query()
    {
        if (empty($_GET['RESERVED_IISREDIRECT']) === false) {
            return '';
        }

        return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : false;
    }

    public static function get($key, $alternative = false)
    {
        $data = Helper::arrayPath($key, $_GET);
        return $data === false ? $alternative : $data;
    }

    public static function post($key, $alternative = false)
    {
        $data = Helper::arrayPath($key, $_POST);
        return $data === false ? $alternative : $data;
    }

    public static function cookie($key, $alternative = false)
    {
        $data = Helper::arrayPath($key, $_COOKIE);
        return $data === false ? $alternative : $data;
    }

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
            return Helper::arrayPath($firstKey, $_FILES);
        }

        return array(
            'tmp_name' => Helper::arrayPath($restKey, $_FILES[$firstKey]['tmp_name']),
            'name'     => Helper::arrayPath($restKey, $_FILES[$firstKey]['name']),
            'type'     => Helper::arrayPath($restKey, $_FILES[$firstKey]['type']),
            'error'    => Helper::arrayPath($restKey, $_FILES[$firstKey]['error']),
            'size'     => Helper::arrayPath($restKey, $_FILES[$firstKey]['size'])
        );
    }

    public static function raw($binary = true)
    {
        if (is_readable('php://input')) {
            $mode = $binary === true ? 'rb' : 'r';

            if (PHP_VERSION_ID >= 50600) {
                return fopen('php://input', $mode);
            }

            $tmp = AppData::createTmp();

            if (copy('php://input', $tmp)) {
                return fopen($tmp, $mode);
            }
        }

        return false;
    }
}
