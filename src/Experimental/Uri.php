<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Helper;

class Uri
{
    /** Use with `Uri::encodepath` for allow path with ascii characters */
    const ASCII = 1;

    /** Use with `Uri::encodepath` for allow path with unicode */
    const UNICODE = 2;

    /**
     * Convert text to URL format
     *
     * @param string $text
     * @param int    $type
     * @return string
     */
    public static function encodepath($text, $type = null)
    {
        $text = preg_replace('#[`\'"\^~\{\}\[\]\(\)]#', '', $text);
        $text = preg_replace('#[\n\s\/\p{P}]#u', '-', $text);

        if ($type === self::UNICODE) {
            $text = preg_replace('#[^\d\p{L}\p{N}\-]#u', '', $text);
        } elseif ($type === self::ASCII) {
            $text = preg_replace('#[^\d\p{L}\-]#u', '', $text);
            $text = self::encodepath($text);
        } else {
            $text = Helper::toAscii($text);
            $text = preg_replace('#[^a-z\d\-]#i', '', $text);
        }

        $text = preg_replace('#-+-#', '-', $text);

        return trim($text, '-');
    }

    /**
     * Canonicalize paths
     *
     * @param string $path
     * @param bool   $backspace
     * @return string
     */
    public static function canonicalize($path, $backspace = true)
    {
        if ($backspace) {
            $path = strtr($path, '\\', '/');
        }

        if (preg_match('#(../|//)#', $path) === 0) {
            return $path;
        }

        while (strpos($path, '../') === 0) {
            $path = substr($path, 3);
        }

        $items = explode('/', $path);
        $canonical = array();

        foreach ($items as $item) {
            if ($item === '..') {
                array_pop($canonical);
            } elseif ($item !== '.' && $item !== '') {
                $canonical[] = $item;
            }
        }

        $path = implode('/', $canonical) . (substr($path, -1) === '/' ? '/' : '');

        $canonical = $items = null;

        return $path;
    }

    /**
     * Normalize URL, include canonicalized path
     *
     * @param string $url
     * @return string
     */
    public static function normalize($url)
    {
        $url = parse_url($url);

        if ($url === false) {
            return $url;
        }

        $scheme = empty($url['scheme']) ? null : strtolower($url['scheme']);

        $normalized = $scheme === 'file' ? 'file://' : ($url['scheme'] . '://');

        if (isset($url['user'])) {
            $normalized .= $url['user'];
            $normalized .= isset($url['pass']) ? (':' . $url['pass']) : '';
            $normalized .= '@';
        }

        if (isset($url['host'])) {
            $isValid = array( 'https', 'https', 'ftp', 'sftp' );
            $normalized .= in_array($scheme, $isValid) ? strtolower($url['host']) : $url['host'];
        }

        if (
        isset($url['port']) &&
        ($url['port'] == 80 && $scheme === 'http') === false &&
        ($url['port'] == 443 && $scheme === 'https') === false &&
        ($url['port'] == 21 && $scheme === 'ftp') === false &&
        ($url['port'] == 22 && $scheme === 'sftp') === false
        ) {
            $normalized .= ':' . $url['port'];
        }

        if (empty($url['path']) || $url['path'] === '/') {
            $normalized .= '/';
        } else {
            $normalized .= '/' . ltrim('/' . self::canonicalize($url['path']), '/');
        }

        if (isset($url['query'])) {
            $normalized .= '?' . $url['query'];
        }

        if (isset($url['fragment'])) {
            $normalized .= '#' . $url['fragment'];
        }

        return $normalized;
    }
}
