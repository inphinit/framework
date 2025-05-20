<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Exception;
use Inphinit\Http\Request;

class Url
{
    const PATH_ASCII = 1;
    const PATH_UNICODE = 2;
    const PATH_SLUG = 4;
    const SORT_QUERY = 8;

    private static $transliterator;

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
     * Parse URL
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $source = $url;
        $fragment = null;
        $querystring = null;

        if (strpos($url, '#') !== false) {
            list($url, $fragment) = explode('#', $url, 2);
        }

        if (strpos($url, '?') !== false) {
            list($url, $querystring) = explode('?', $url, 2);
        }

        if (preg_match('#^[A-Z]\:#i', $url)) {
            $url = 'file:///' . $url;
        }

        $restore = array('%40' => '@', '%3A' => ':', '%5C' => '\\');
        $extract = explode('/', $url);

        foreach ($extract as &$value) {
            $value = strtr(rawurlencode($value), $restore);
        }

        $url = implode('/', $extract);

        $data = parse_url($url);

        if ($data === false) {
            throw new Exception('Invalid URL');
        }

        foreach ($data as &$value) {
            $value = rawurldecode($value);
        }

        $data += $this->data;
        $data['fragment'] = $fragment;
        $data['query'] = $querystring;
        $data['source'] = $source;

        if (isset($data['scheme']) && strcasecmp($data['scheme'], 'file') === 0) {
            $data['path'] = '/' . ltrim($data['path'], '/');
        }

        $this->data = $data + $this->data;
    }

    /**
     * Sets default ports
     *
     * @param array $dict
     * @return void
     */
    public static function setDefaultPorts(array $ports)
    {
        self::$defaultPorts = $ports;
    }

    /**
     * Sets slug dictionary
     *
     * @param array $dict
     * @return void
     */
    public static function setSlugDict(array $dict)
    {
        self::$slugDict = $dict;
    }

    /**
     * Get Url instance from current url
     *
     * @param bool $query
     * @return \Inphinit\Utility\Url
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
     * @return void
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
                $path = \strtolower($path);
            } elseif ($configs & self::PATH_UNICODE) {
                if (self::$transliterator === null) {
                    self::$transliterator = \Transliterator::create('Any-Lower');
                }

                $path = self::$transliterator->transliterate($path);
            }

            if ($configs & self::PATH_SLUG) {
                $path = strtr($path, self::$slugDict);
                $path = preg_replace('#[^\/\-\pL\pN\s_]+#u', '', $path);
                $path = preg_replace('#[\s\-_]+#', '-', $path);
                $path = str_replace(array('/-', '-/'), '/', $path);
                $path = preg_replace('#//+#', '/', $path);
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
     * @return string
     */
    public static function canonpath($path)
    {
        $slash = strpos($path, '/');
        $backSlash = strpos($path, '\\');

        if (strpos($path, '\\') !== false) {
            $separator = '\\';
        } elseif (strpos($path, '/') !== false) {
            $separator = '/';
        } else {
            return $path;
        }

        $prependSeparator = substr($path, 0, 1) === $separator;
        $appendSeparator = substr($path, -1) === $separator;

        $parts = explode($separator, trim($path, $separator));
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

        $path = '';

        if ($prependSeparator) {
            $path .= $separator;
        }

        $path .= implode($separator, $rebuild);

        if ($appendSeparator) {
            $path .= $separator;
        }

        $rebuild = null;

        return $path;
    }

    /**
     * Get value for a URL component
     *
     * @param string $name
     * @return string|null
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Set value for a URL component
     *
     * @param string $name
     * @param string $value|null
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->data)) {
            $this->data[$name] = $value ? (string) $value : null;
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

        if (isset(self::$defaultPorts[$scheme]) && self::$defaultPorts[$scheme] === $port) {
            $port = '';
        } else {
            $port = $this->data['port'] ? ':' . $this->data['port'] : '';
        }

        $path = $this->data['path'] ? $this->data['path'] : '';
        $user = $this->data['user'] ? $this->data['user'] : '';
        $pass = $this->data['pass'] ? (':' . $this->data['pass']) : '';

        $pass = $user || $pass ? ($pass . '@') : '';

        if ($host) {
            $scheme .= '://';
        } elseif ($scheme === 'file') {
            $scheme .= '://';

            if (preg_match('#^[A-Z]:#', $path)) {
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
