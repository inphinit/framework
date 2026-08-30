<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Exception;
use Inphinit\Http\Request;

/**
 * @property string $scheme
 * @property string $host
 * @property string $port
 * @property string $user
 * @property string $pass
 * @property string $path
 * @property string $query
 * @property string $fragment
 */
class Url
{
    /** @var int Used by the `::normalize()` method to convert the path to ASCII */
    const PATH_ASCII = 1;

    /** @var int Used by the `::normalize()` method to convert the path to lower unicode */
    const PATH_UNICODE = 2;

    /** @var int Used by the `::normalize()` method to convert spaces, underscore to scapes and remove unused characteres */
    const PATH_SLUG = 4;

    /** @var int Used by the `::normalize()` method to sort querystring */
    const SORT_QUERY = 8;

    private static $schemaPorts = array(
        'ftp' => 21,
        'sftp' => 22,
        'http' => 80,
        'https' => 443
    );

    private static $slugDict = array(
        '@' => '-at-'
    );

    private static $transliterator;

    private $components = array(
        'scheme' => null,
        'host' => null,
        'port' => null,
        'user' => null,
        'pass' => null,
        'path' => null,
        'query' => null,
        'fragment' => null
    );

    private $cache;

    /**
     * Parse URL
     *
     * @param string $url
     * @throws \Inphinit\Exception
     */
    public function __construct($url)
    {
        if (preg_match('#^[A-Z]\:#i', $url)) {
            $url = 'file:///' . $url;
        }

        // Prevent unicode conflicts with parser
        $encoded = self::encode($url);

        $components = parse_url($encoded);

        if ($components === false) {
            throw new Exception('Unrecognized or corrupted URL format: ' . $url);
        }

        foreach ($components as &$component) {
            $component = rawurldecode($component);
        }

        $this->components = $components + $this->components;
    }

    /**
     * Sets default ports associated to specific schemas
     *
     * @param array $ports
     */
    public static function setSchemaPorts(array $ports)
    {
        self::$schemaPorts = $ports;
    }

    /**
     * Sets slug dictionary
     *
     * @param array<string, string> $dict
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
     */
    public function normalize($configs = 0)
    {
        if ($this->components['scheme']) {
            $this->components['scheme'] = strtolower($this->components['scheme']);
        }

        $path = $this->components['path'];

        if ($path) {
            $path = self::canonpath($path);

            if ($this->components['scheme'] === 'file' && $path[0] === '/' && strpos($path, ':') === 2) {
                $path = ltrim($path, '/');
            }

            if ($configs & self::PATH_ASCII) {
                $items = explode('/', $path);

                foreach ($items as &$item) {
                    $item = Strings::toAscii($item);
                    $item = str_replace('/', '-', $item);
                }

                $path = implode('/', $items);
                $path = strtolower($path);
            } elseif ($configs & self::PATH_UNICODE) {
                if (self::$transliterator === null) {
                    self::$transliterator = \Transliterator::create('Any-Lower');
                }

                $path = self::$transliterator->transliterate($path);
            }

            if ($configs & self::PATH_SLUG) {
                $path = strtr($path, self::$slugDict);
                $path = preg_replace('#[^\(\)\[\]\/\-\pL\pN\s_]+#u', '', $path);
                $path = preg_replace('#[\s\-_]+#', '-', $path);
                $path = str_replace(array('/-', '-/'), '/', $path);
                $path = preg_replace('#//+#', '/', $path);
            }

            $this->components['path'] = $path;
            $this->cache = null;
        }

        if ($this->components['query'] && ($configs & self::SORT_QUERY)) {
            parse_str($this->components['query'], $query);

            if ($query) {
                Arrays::ksort($query);
                $this->components['query'] = http_build_query($query);
                $this->cache = null;
            }
        }
    }

    /**
     * Resolve paths with `..` and `.`
     *
     * @param string $path
     * @return string
     */
    public static function canonpath($path)
    {
        if (strpos($path, '\\') !== false) {
            $segment = '\\.\\';
            $separator = '\\';
        } elseif (strpos($path, '/') !== false) {
            $segment = '/./';
            $separator = '/';
        } else {
            return $path;
        }

        $prepend_separator = substr($path, 0, 1) === $separator;
        $append_separator = substr($path, -1) === $separator;

        $path = str_replace($segment, $separator, $path);
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

        if ($prepend_separator) {
            $path .= $separator;
        }

        $path .= implode($separator, $rebuild);

        if ($append_separator) {
            $path .= $separator;
        }

        $rebuild = null;

        return $path;
    }

    /**
     * Encode URL while preserving the URI delimiters
     * `:`, `/`, `@`, `?`, `&`, `=`, `#`, `[`, `]`, `(`, `)`, `_` and `-`
     *
     * @param string $url
     * @return string
     */
    public static function encode($url)
    {
        return preg_replace_callback('~[^:/@?&=#\[\]\(\)_\-]+~sD', function ($matches) {
            return rawurlencode($matches[0]);
        }, $url);
    }

    /**
     * Get value for a URL component
     *
     * @param string $name
     * @return string|null
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->components) === false) {
            throw new Exception('Unexpected URL component: ' . $name);
        }

        return $this->components[$name];
    }

    /**
     * Set value for a URL component
     *
     * @param string      $name
     * @param string|null $value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->components) === false) {
            throw new Exception('Unexpected URL component: ' . $name);
        }

        if ($value !== null) {
            if ($name === 'port') {
                if (is_numeric($value) === false || preg_match('#^(0|[1-9]\d*)$#', $value) === false) {
                    throw new Exception('port expects a numeric value');
                }
            } elseif (is_string($value) === false || $value === '') {
                throw new Exception($name . ' expects a non-empty string');
            }
        }

        $this->cache = null;
        $this->components[$name] = $value;
    }

    /**
     * Compose string
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $components = $this->components;

        $scheme = $components['scheme'];
        $user = $components['user'];
        $pass = $components['pass'];
        $auth = '';

        if ($user) {
            $auth .= $user;
        }

        if ($pass) {
            $auth .= ':' . $pass;
        }

        if ($auth !== '') {
            $auth .= '@';
        }

        $host = $components['host'] ? $components['host'] : '';
        $port = $components['port'];

        if ($scheme && isset(self::$schemaPorts[$scheme]) && self::$schemaPorts[$scheme] == $port) {
            $port = '';
        } elseif ($port) {
            $port = ':' . $port;
        }

        $path = $components['path'] ? $components['path'] : '';
        $query = $components['query'] ? ('?' . $components['query']) : '';
        $fragment = $components['fragment'] ? ('#' . $components['fragment']) : '';

        if ($host) {
            $scheme .= '://';
        } elseif ($scheme === 'file') {
            $scheme .= '://';

            if (preg_match('#^[A-Z]:#i', $path)) {
                $scheme .= '/';
            }
        } elseif ($scheme) {
            $scheme .= ':';
        }

        $this->cache = $scheme . $auth . $host . $port . $path . $query . $fragment;

        return $this->cache;
    }
}
