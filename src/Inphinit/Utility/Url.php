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
    const PATH_CLEAR = 1;
    const PATH_ASCII = 2;
    const PATH_UNICODE = 4;
    const PATH_NORMALIZE = 8;
    const SORT_QUERY = 16;

    /** @var array Default schemes */
    protected $knownSchemes = array('file', 'ftp', 'sftp', 'http', 'https');

    /** @var array Default ports */
    protected $defaultPorts = array('ftp' => 21, 'sftp' => 22, 'http' => 80, 'https' => 443);

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
     * xxxx
     *
     * @param string $url
     */
    public function __construct($url)
    {
        $data = parse_url($url);

        if ($data === false) {
            throw new Exception($url . ' is invalid', 0, 2);
        }

        $data['source'] = $url;

        if (isset($data['scheme']) && strcasecmp($data['scheme'], 'file') === 0) {
            $data['path'] = '/' . ltrim($data['path'], '/');
        }

        $this->data = $data + $this->data;
    }

    /**
     * Get current url
     */
    public static function current()
    {
        $url = INPHINIT_URL;
        $query = Request::query();

        if ($query) {
            $url .= '?' . $query;
        }

        return new static($url);
    }

    /**
     * [xxxxxxxxxxx]
     *
     * @param string $value
     * @return array|string
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
    }

    /**
     * [xxxxxxxxxxx]
     *
     * @param string $value
     */
    public function __set($name, $value)
    {
        if (array_key_exists($name, $this->data)) {
            if (is_string($value) === false) {
                throw new Exception(get_class($this) . '::$' . $name . ' except an string', 0, 2);
            }

            $this->data[$name] = $value;
        }
    }

    public function normalize($configs = null)
    {
        if ($this->data['scheme']) {
            $this->data['scheme'] = strtolower($this->data['scheme']);
        }

        $path = $this->data['path'];

        if ($path) {
            $path = self::canonpath($path, $configs);

            if ($this->data['scheme'] === 'file' && $path[0] === '/' && strpos($path, ':') === 2) {
                $path = ltrim($path, '/');
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

    public static function canonpath($path, $configs = 0)
    {
        if ($configs === null) {
            $configs = self::PATH_NORMALIZE;
        }

        $separator = '/';

        if (strpos($path, '\\') !== false) {
            $separator = '\\';
            $path = preg_replace('#\\\\+#', '\\', $path);
        } else {
            $path = preg_replace('#//+#', '/', $path);
        }

        $parts = explode($separator, $path);
        $rebuild = array();

        foreach ($parts as $part) {
            if ($part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($rebuild);
            } else {
                $rebuild[] = $part;
            }
        }

        $path = implode('/', $rebuild);

        $rebuild = null;

        if ($configs & self::PATH_CLEAR) {
            $path = preg_replace('#[`\'"\^~{}\[\]()]#', '', $path);
            $path = preg_replace('#[\n\s_]#', '-', $path);
        }

        if ($configs & self::PATH_UNICODE) {
            $path = preg_replace('#[^\d\p{L}\p{N}\/\-]#u', '', $path);
        } elseif ($configs & self::PATH_ASCII) {
            $path = preg_replace('#[^\d\p{L}\/\-]#u', '', $path);
        }

        if ($configs & self::PATH_CLEAR) {
            $path = trim(preg_replace('#--+#', '-', $path), '-');
        }

        return $path;
    }

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

        $pass = $user || $pass ? $pass . '@' : '';

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
