<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Exception;
use Inphinit\Http\Request;
use Inphinit\Utility\Arrays;
use Inphinit\Utility\Strings;

class Url
{
    const PATH_ASCII = 1;
    const PATH_UNICODE = 2;
    const PATH_NORMALIZE = 4;
    const PATH_SLUG = 8;
    const SORT_QUERY = 16;

    private static $defaultPorts = array(
        'ftp' => 21,
        'sftp' => 22,
        'http' => 80,
        'https' => 443
    );

    private static $slugDict = array(
        '@' => '-at-'
    );

    private $data = array(
        'source' => null,
        'scheme' => null,
        'host' => null,
        'port' => null,
        'user' => null,
        'pass' => null,
        'path' => null,
        'query' => null,
        'fragment' => null
    );

    /**
     * Sets default ports
     *
     * @param array $dict
     */
    public static function setDefaultPorts(array $ports)
    {
        self::$defaultPorts = $ports;
    }

    /**
     * Sets slug dictionary
     *
     * @param array $dict
     */
    public static function setSlugDict(array $dict)
    {
        self::$slugDict = $dict;
    }

    /**
     * Parse URL
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $data = parse_url($url);

        if ($data === false) {
            throw new Exception($url . ' is invalid');
        }

        $data['source'] = $url;

        if (isset($data['scheme']) && strcasecmp($data['scheme'], 'file') === 0) {
            $data['path'] = '/' . ltrim($data['path'], '/');
        }

        $this->data = $data + $this->data;
    }

    /**
     * Get Url instance from current url
     *
     * @param bool $query
     */
    public static function application($query)
    {
        $url = INPHINIT_URL;

        if ($query && ($qs = Request::query())) {
            $url .= '?' . $qs;
        }

        return new static($url);
    }

    /**
     * Normalize path and querystring
     *
     * @param int $configs
     */
    public function normalize($configs = 0)
    {
        if ($this->data['scheme']) {
            $this->data['scheme'] = strtolower($this->data['scheme']);
        }

        $path = $this->data['path'];

        if ($path) {
            $path = self::canonpath($path);

            if ($this->data['scheme'] === 'file' && $path[0] === '/' && strpos($path, ':') === 2) {
                $path = ltrim($path, '/');
            }

            if ($configs & self::PATH_ASCII) {
                $path = Strings::toAscii($path);
            } elseif ($configs & self::PATH_UNICODE) {
                $path = mb_strtolower($path);
            }

            if ($configs & self::PATH_SLUG) {
                $path = strtr($path, self::$slugDict);
                $path = preg_replace('#[^\/\-\pL\pN\s]+#u', '', $path);
                $path = preg_replace('#[\s\-]+#u', '-', $path);
            }

            $this->data['path'] = $path;
        }

        if ($this->data['query'] && ($configs & self::SORT_QUERY)) {
            parse_str($this->data['query'], $query);

            if ($query) {
                Arrays::ksort($query);
                $this->data['query'] = http_build_query($query);
            }
        }
    }

    /**
     * Canon path
     *
     * @param string $path
     */
    public static function canonpath($path)
    {
        $separator = strpos($path, '\\') !== false ? '\\' : '/';

        $parts = explode($separator, trim($path, '/'));
        $rebuild = array();

        foreach ($parts as $part) {
            if ($part !== '' && $part !== '.') {
                if ($part === '..') {
                    array_pop($rebuild);
                } else {
                    $rebuild[] = $part;
                }
            }
        }

        $path = '/' . implode('/', $rebuild) . '/';

        $rebuild = null;

        return $path;
    }

    /**
     * Get value for a URL component
     *
     * @param string $value
     * @return string
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Set value for a URL component
     *
     * @param string $value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->data)) {
            if (is_string($value) === false) {
                throw new Exception(get_class($this) . '::$' . $name . ' except an string');
            }

            $this->data[$name] = $value;
        }
    }

    /**
     * Compose string
     *
     * @return string
     */
    public function __toString()
    {
        $scheme = $this->data['scheme'];
        $host = $this->data['host'] ? $this->data['host'] : '';
        $port = $this->data['port'];

        if (isset($this->defaultPorts[$scheme]) && $this->defaultPorts[$scheme] === $port) {
            $port = '';
        } else {
            $port = $this->data['port'] ? ':' . $this->data['port'] : '';
        }

        $path = $this->data['path'] ? $this->data['path'] : '';
        $user = $this->data['user'] ? $this->data['user'] : '';
        $pass = $this->data['pass'] ? ':' . $this->data['pass'] : '';

        $pass = $user || $pass ? ($pass . '@') : '';

        if ($host) {
            $scheme .= '://';
        } elseif ($scheme === 'file') {
            $scheme .= '://';

            if ($path[0] === '/' && strpos($path, ':') === 2) {
                $scheme .= '/';
            }
        } elseif ($scheme) {
            $scheme .= ':';
        }

        $query = $this->data['query'] ? '?' . $this->data['query'] : '';
        $fragment = $this->data['fragment'] ? '#' . $this->data['fragment'] : '';

        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }

    public function __destruct()
    {
        $this->data = null;
    }
}
