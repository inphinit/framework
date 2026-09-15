<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Diagnostics\Inspector;
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
    /** @var int Used by the `::modify()` method to align scheme case to schemePorts */
    const SCHEME_ALIGN = 1;

    /** @var int Used by the `::modify()` method to convert domain to IDNA ASCII form (UTS #46) */
    const HOST_IDNA_ASCII = 2;

    /** @var int Used by the `::modify()` method to convert the path to ASCII */
    const PATH_ASCII = 4;

    /** @var int Used by the `::modify()` resolve path with `..` and `.` */
    const PATH_RESOLVE = 8;

    /** @var int Used by the `::modify()` method to convert spaces and underscores into hyphens and remove unused characters */
    const PATH_SLUG = 16;

    /** @var int Used by the `::modify()` method to convert the path to lower unicode */
    const PATH_UNICODE = 32;

    /** @var int Used by the `::modify()` method to sort querystring */
    const SORT_QUERY = 64;

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

    private static $schemePorts = array(
        'ftp' => 21,
        'sftp' => 22,
        'http' => 80,
        'https' => 443,
        'ws' => 80,
        'wss' => 443
    );

    private static $slugDict = array(
        '@' => '-at-'
    );

    private $cache;
    private static $anyLower;

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
        $encoded = self::encode($url, '#&()-/:=?@[]_\\', true);

        $components = parse_url($encoded);

        if ($components === false) {
            throw new Exception('Unrecognized or corrupted URL format: ' . $url);
        }

        foreach ($components as $component => $value) {
            if ($component === 'port') {
                $value = self::parsePort($value);
            } else {
                $value = rawurldecode($value);
            }

            if ($component === 'query') {
                \parse_str($value, $querystring);
                $value = $querystring;
            }

            $this->components[$component] = $value;
        }
    }

    /**
     * Set default ports associated with specific schemes.
     *
     * @param array<string, int> $ports
     */
    public static function setSchemePorts(array $ports)
    {
        self::$schemePorts = $ports;
    }

    /**
     * Set slug dictionary
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
     * @param bool $appendQuery
     * @return \Inphinit\Utility\Url
     */
    public static function application($appendQuery)
    {
        $url = INPHINIT_URL;

        if ($appendQuery && ($qs = Request::query())) {
            $url .= '?' . $qs;
        }

        return new static($url);
    }

    /**
     * Creates a new object with modified:
     * - Scheme is modified if the SCHEME_ALIGN flag is used
     * - Host is modified if the HOST_IDNA_ASCII flag is used
     * - Path is modified if the PATH_ASCII, PATH_UNICODE, or PATH_SLUG flags are used
     * - Query fields are sorted if the SORT_QUERY flag is used
     *
     * @param int $flags
     * @return \Inphinit\Utility\Url
     */
    public function modify($flags)
    {
        $valid_flags = (
            self::SCHEME_ALIGN |
            self::HOST_IDNA_ASCII |
            self::PATH_RESOLVE |
            self::PATH_ASCII |
            self::PATH_UNICODE |
            self::PATH_SLUG |
            self::SORT_QUERY
        );

        if (is_int($flags) === false || ($flags & ~$valid_flags) !== 0) {
            throw new Exception('Invalid flags');
        }

        $components = $this->components;
        $scheme = $components['scheme'];
        $host = $components['host'];
        $path = $components['path'];
        $query = $components['query'];

        if ($scheme !== null && ($flags & self::SCHEME_ALIGN)) {
            foreach (self::$schemePorts as $schemePort => $port) {
                if (strcasecmp($scheme, $schemePort) === 0) {
                    $scheme = $schemePort;
                    break;
                }
            }
        }

        if ($host !== null && ($flags & self::HOST_IDNA_ASCII)) {
            $host_ascii = \idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46, $idna_info);

            if ($host_ascii === false) {
                throw new Exception('Cannot convert domain to ascii: ' . self::getIdnaError($idna_info));
            }

            $host = $host_ascii;
        }

        if ($path !== null) {
            if ($scheme === 'file' && $path[0] === '/' && strpos($path, ':') === 2) {
                $path = ltrim($path, '/');
            }

            if ($flags & self::PATH_RESOLVE) {
                $path = self::resolvePath($path);
            }

            if ($flags & self::PATH_ASCII) {
                $items = explode('/', $path);

                foreach ($items as &$item) {
                    $item = Strings::ascii($item);
                    $item = str_replace('/', '-', $item);
                }

                $path = implode('/', $items);
                $path = strtolower($path);
            } elseif ($flags & self::PATH_UNICODE) {
                if (self::$anyLower === null) {
                    self::$anyLower = \Transliterator::create('Any-Lower');
                }

                $path = self::$anyLower->transliterate($path);
            }

            if ($flags & self::PATH_SLUG) {
                $path = strtr($path, self::$slugDict);
                $path = preg_replace('#[^\(\)\[\]\/\\\\\:\-\pL\pN\s_]+#u', '', $path);
                $path = preg_replace('#[\s\-_]+#u', '-', $path);
                $path = str_replace(array('/-', '-/'), '/', $path);
                $path = preg_replace('#//+#', '/', $path);
            }
        }

        if ($query !== null && ($flags & self::SORT_QUERY)) {
            Arrays::ksort($query);
        }

        $components['scheme'] = $scheme;
        $components['host'] = $host;
        $components['path'] = $path;
        $components['query'] = $query;

        $instance = new Url('/');

        foreach ($components as $component => $value) {
            $instance->{$component} = $value;
        }

        return $instance;
    }

    /**
     * Creates a new object with modified component
     *
     * @param string $target
     * @param string $newValue
     * @return \Inphinit\Utility\Url
     */
    public function with($target, $newValue)
    {
        $instance = new Url('/');

        try {
            $instance->{$target} = $newValue;
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }

        foreach ($this->components as $component => $value) {
            if ($target !== $component) {
                $instance->{$component} = $value;
            }
        }

        return $instance;
    }

    /**
     * Get value for a URL component
     * Note: `$instance->query` returns an array if the component is present
     *
     * @param string $name
     * @return string|array|null
     */
    public function __get($component)
    {
        if (array_key_exists($component, $this->components) === false) {
            throw new Exception('Unexpected URL component');
        }

        return $this->components[$component];
    }

    /**
     * Set value for a URL component
     *
     * @param string $component
     * @param string|array|null $value
     */
    public function __set($component, $value)
    {
        if (array_key_exists($component, $this->components) === false) {
            throw new Exception('Unexpected URL component');
        }

        if ($value !== null) {
            if ($component === 'port') {
                $value = self::parsePort($value);
            }

            if ($component === 'query') {
                if (is_array($value) === false) {
                    $type = Inspector::type($value);
                    throw new Exception("`query` expects to be array, {$type} given");
                }
            } elseif (is_string($value) === false) {
                $type = Inspector::type($value);
                throw new Exception("`{$component}` expects to be string, {$type} given");
            }

            if ($component === 'scheme' && preg_match('#^[a-z][a-z\d+.-]*$#i', $value) !== 1) {
                throw new Exception('Invalid scheme');
            }
        }

        $this->components[$component] = $value;

        $this->cache = null;
    }

    /**
     * Returns a string encoding only what is necessary
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $components = $this->components;

        $user = $components['user'];
        $pass = $components['pass'];
        $auth = '';

        if ($user) {
            $auth .= self::encode($user, '#/:?@\\', false);
        }

        if ($pass) {
            $auth .= ':' . self::encode($pass, '#/:?@\\', false);
        }

        if ($auth !== '') {
            $auth .= '@';
        }

        $scheme = $components['scheme'] === null ? '' : $components['scheme'];
        $host = $components['host'] === null ? '' : $components['host'];
        $port = $components['port'] === null ? '' : $components['port'];
        $path = $components['path'] === null ? '' : $components['path'];
        $query = $components['query'];
        $fragment = $components['fragment'] === null ? '' : $components['fragment'];

        if ($scheme !== '' && isset(self::$schemePorts[$scheme]) && self::$schemePorts[$scheme] == $port) {
            $port = '';
        } elseif ($port !== '') {
            $port = ':' . $port;
        }

        if ($host !== '') {
            $scheme .= '://';
        } elseif ($scheme === 'file') {
            $scheme .= '://';

            if (preg_match('#^[A-Z]:#i', $path)) {
                $scheme .= '/';
            }
        } elseif ($scheme !== '') {
            $scheme .= ':';
        }

        if ($path !== '') {
            $path = self::encode($path, "#?\n\r\t\v\x00", false);
        }

        if ($query !== null && count($query) !== 0) {
            $query = '?' . \rawurldecode(\http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        } else {
            $query = '';
        }

        if ($fragment !== '') {
            $fragment = '#' . self::encode($fragment, "\n\r\t\v\x00", false);
        }

        $this->cache = $scheme . $auth . $host . $port . $path . $query . $fragment;

        return $this->cache;
    }

    private static function encode($string, $chars, $preserveChars)
    {
        $delimiters = ($preserveChars ? '^' : '') . preg_quote($chars, '~');

        return preg_replace_callback('~[' . $delimiters . ']+~sD', function ($matches) {
            return rawurlencode($matches[0]);
        }, $string);
    }

    private static function resolvePath($path)
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

    private static function parsePort($port)
    {
        if (is_string($port) && ctype_digit($port)) {
            $port = ltrim($port, '0');
        } elseif (is_int($port) === false) {
            throw new Exception('Invalid port', 0, 3);
        }

        if ($port === '' || $port < 1 || $port > 65535) {
            throw new Exception('Invalid port range', 0, 3);
        }

        return (string) $port;
    }

    private static function getIdnaError($info)
    {
        if (isset($info['errors']) === false) {
            return 'unknown';
        }

        $errors = $info['errors'];

        if ($errors & \IDNA_ERROR_EMPTY_LABEL) {
            return 'A non-final domain name label (or the whole domain name) is empty';
        }

        if ($errors & \IDNA_ERROR_LABEL_TOO_LONG) {
            return 'A domain name label is longer than 63 bytes';
        }

        if ($errors & \IDNA_ERROR_DOMAIN_NAME_TOO_LONG) {
            return 'A domain name is longer than 255 bytes in its storage form';
        }

        if ($errors & \IDNA_ERROR_LEADING_HYPHEN) {
            return 'A label starts with a hyphen-minus (-)';
        }

        if ($errors & \IDNA_ERROR_TRAILING_HYPHEN) {
            return 'A label ends with a hyphen-minus (-)';
        }

        if ($errors & \IDNA_ERROR_HYPHEN_3_4) {
            return 'A label contains hyphen-minus (-) in the third and fourth positions';
        }

        if ($errors & \IDNA_ERROR_LEADING_COMBINING_MARK) {
            return 'A label starts with a combining mark';
        }

        if ($errors & \IDNA_ERROR_DISALLOWED) {
            return 'A label or domain name contains disallowed characters';
        }

        if ($errors & \IDNA_ERROR_PUNYCODE) {
            return 'A label starts with "xn--" but does not contain valid Punycode';
        }

        if ($errors & \IDNA_ERROR_LABEL_HAS_DOT) {
            return 'A label contains a dot=full stop';
        }

        if ($errors & \IDNA_ERROR_INVALID_ACE_LABEL) {
            return 'An ACE label does not contain a valid label string';
        }

        if ($errors & \IDNA_ERROR_BIDI) {
            return 'A label does not meet the IDNA BiDi requirements';
        }

        if ($errors & \IDNA_ERROR_CONTEXTJ) {
            return 'A label does not meet the IDNA CONTEXTJ requirements';
        }

        return 'unknown';
    }
}
