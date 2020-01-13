<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Uri
{
    /** Use with `Uri::encodepath` for allow path with ascii characters */
    const ASCII = 1;

    /** Use with `Uri::encodepath` for allow path with unicode */
    const UNICODE = 2;

    /**
     * Define default port to schemes, you can customize in a extended class
     * If put a new port with scheme he is removed in final url, eg. `'myscheme:900',`
     * `ClassExtendedFromUri::normalize('myscheme://host:900/test')` => `myscheme://host/test`
     *
     * @var array
     */
    protected static $defaultPorts = array( 'http:80', 'https:443', 'ftp:21', 'sftp:22' );

    /**
     * Define default schemes, you can customize in a extended class
     * If put a new scheme the host is converted to lower-case, eg. `'whatsapp',`
     * `Uri::normalize('whatsapp://SEND?text=foo')` => `whatsapp://send?text=foo`
     *
     * @var array
     */
    protected static $defaultSchemes = array( 'http', 'https', 'ftp', 'sftp' );

    /**
     * Convert text to URL format
     *
     * @param string $path
     * @param int    $type
     * @return string
     */
    public static function encodepath($path, $type = null)
    {
        $path = preg_replace('#[`\'"\^~{}\[\]()]#', '', $path);
        $path = preg_replace('#[\n\s\/\p{P}]#u', '-', $path);

        if ($type === self::UNICODE) {
            $path = preg_replace('#[^\d\p{L}\p{N}\-]#u', '', $path);
        } elseif ($type === self::ASCII) {
            $path = preg_replace('#[^\d\p{L}\-]#u', '', $path);
            $path = self::encodepath($path);
        } else {
            $path = Helper::toAscii($path);
            $path = preg_replace('#[^a-z\d\-]#i', '', $path);
        }

        return trim(preg_replace('#--+#', '-', $path), '-');
    }

    /**
     * Canonicalize paths
     *
     * @param string $path
     * @return string
     */
    public static function canonpath($path)
    {
        $path = strtr($path, '\\', '/');

        if (preg_match('#\.\./|//|\./#', $path) === 0) {
            return $path;
        }

        $path = preg_replace('#//+#', '/', $path);

        $canonical = array();

        foreach (explode('/', $path) as $item) {
            if ($item === '..') {
                array_pop($canonical);
            } elseif ($item !== '.') {
                $canonical[] = $item;
            }
        }

        $path = implode('/', $canonical);

        $canonical = null;

        return $path;
    }

    /**
     * Normalize URL, include canonicalized path
     *
     * @param string $url
     * @return string|bool
     */
    public static function normalize($url)
    {
        $u = parse_url(preg_replace('#^file:/+([a-z]+:)#i', '$1', $url));

        if ($u === false) {
            return $url;
        }

        if (isset($u['scheme']) === false) {
            $u = null;
            return false;
        }

        $scheme = $u['scheme'];

        if (isset($scheme[1])) {
            $normalized = strtolower($scheme) . '://';
        } else {
            $normalized = strtoupper($scheme) . ':';
        }

        if (isset($u['user'])) {
            $normalized .= $u['user'];
            $normalized .= isset($u['pass']) ? (':' . $u['pass']) : '';
            $normalized .= '@';
        }

        if (isset($u['host'])) {
            if (in_array($scheme, static::$defaultSchemes)) {
                $host = urldecode($u['host']);
                $normalized .= mb_strtolower($host, mb_detect_encoding($host));
            } else {
                $normalized .= $u['host'];
            }
        }

        if (isset($u['port']) && !in_array($scheme . ':' . $u['port'], static::$defaultPorts)) {
            $normalized .= ':' . $u['port'];
        }

        if (empty($u['path']) || $u['path'] === '/') {
            $normalized .= '/';
        } else {
            $normalized .= '/' . ltrim(self::canonpath($u['path']), '/');
        }

        if (isset($u['query'])) {
            $normalized .= self::canonquery($u['query'], '?');
        }

        if (isset($u['fragment'])) {
            $normalized .= '#' . $u['fragment'];
        }

        $u = null;
        return $normalized;
    }

    /**
     * Create URL based in public root application, for example,
     * if you install inphinit in a sub path like: `http://site/foo/bar/myapplication/`
     *
     * @param string $path
     * @return string
     */
    public static function root($path = '')
    {
        return rtrim(INPHINIT_URL, '/') . ($path ? self::canonpath($path) : $path);
    }

    /**
     * Reorder querystring by "keys"
     * if: `Uri::canonquery('z=1&u=2&a=5')` returns `a=5&u=2&z=1`
     *
     * @param string $path
     * @param string $prefix
     * @return string
     */
    public static function canonquery($query, $prefix = '')
    {
        parse_str(preg_replace('#^\?#', '', $query), $args);

        if (empty($args)) {
            return '';
        }

        Helper::ksort($args);
        return $prefix . http_build_query($args);
    }
}
