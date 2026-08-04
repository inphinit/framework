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

        $encoded = self::urlEncode($url);

        $parsed = parse_url($encoded);

        if ($parsed === false) {
            throw new Exception('Unrecognized or corrupted URL format: ' . $url);
        }

        foreach ($parsed as &$value) {
            $value = urldecode($value);
        }

        $this->data = $parsed + $this->data;
    }

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

            $this->data['path'] = $path;
            $this->cache = null;
        }

        if ($this->data['query'] && ($configs & self::SORT_QUERY)) {
            parse_str($this->data['query'], $query);

            if ($query) {
                Arrays::ksort($query);
                $this->data['query'] = http_build_query($query);
                $this->cache = null;
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
        if (strpos($path, '\\') !== false) {
            $separator = '\\';
        } elseif (strpos($path, '/') !== false) {
            $separator = '/';
        } else {
            return $path;
        }

        $prepend_separator = substr($path, 0, 1) === $separator;
        $append_separator = substr($path, -1) === $separator;

        $path = str_replace('/./', '/', $path);
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
     * @param string      $name
     * @param string|null $value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->data) === false) {
            throw new Exception('Invalid URL component: ' . $name);
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
        $this->data[$name] = $value;
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

        $data = $this->data;

        $scheme = $data['scheme'];
        $host = $data['host'] ? $data['host'] : '';
        $port = $data['port'];

        if ($scheme && isset(self::$defaultPorts[$scheme]) && self::$defaultPorts[$scheme] == $port) {
            $port = '';
        } else {
            $port = $data['port'] ? (':' . $data['port']) : '';
        }

        $path = $data['path'] ? $data['path'] : '';
        $user = $data['user'] ? $data['user'] : '';
        $pass = $user && $data['pass'] ? (':' . $data['pass'] . '@') : '';
        $query = $data['query'] ? ('?' . $data['query']) : '';
        $fragment = $data['fragment'] ? ('#' . $data['fragment']) : '';

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

        $this->cache = self::urlEncode($scheme . $user . $pass . $host . $port . $path . $query . $fragment);

        return $this->cache;
    }

    private static function urlEncode($url)
    {
        return preg_replace_callback('~[^:/@?&=#]+~usD', function ($matches) {
            return urlencode($matches[0]);
        }, $url);
    }
}
