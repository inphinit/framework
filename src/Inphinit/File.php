<?php
/*
 * Inphinit
 *
 * Copyright (c) 2023 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Uri;

class File
{
    /**
     * Check if file exists using case-sensitive,
     * For help developers who using Windows OS and using unix-like for production
     *
     * @param string $path
     * @return bool
     */
    public static function exists($path)
    {
        $rpath = realpath($path);

        if ($rpath === false) {
            return false;
        }

        $path = preg_replace('#^file:/+([a-z]:/|/)#i', '$1', strtr($path, '\\', '/'));
        $rpath = strtr($rpath, '\\', '/');

        if ($path !== $rpath) {
            $dir = dirname($path);

            if ($dir === '.') {
                $dir = '';
            } elseif (preg_match('#^[.a-z0-9]+(\/|$)#i', $dir)) {
                $dir = getcwd() . '/' . $dir . '/';

                if (preg_match('#^\.\.\/|\/\.\.\/|\/\.\.$#', $dir)) {
                    $dir = Uri::canonpath($dir);
                }
            }

            $path = $dir . basename($path);

            return $rpath === $path || substr($rpath, strlen($rpath) - strlen($path)) === $path;
        }

        return true;
    }

    /**
     * Get file/folder permissions in a format more readable.
     * Return `false` if file is not found
     *
     * @param string $path
     * @param bool   $full
     * @return string|bool
     */
    public static function permission($path, $full = false)
    {
        if (self::exists($path) === false) {
            return false;
        }

        $perms = fileperms($path);

        if ($full !== true) {
            return substr(sprintf('%o', $perms), -4);
        }

        if (($perms & 0xC000) === 0xC000) {
            $info = 's';
        } elseif (($perms & 0xA000) === 0xA000) {
            $info = 'l';
        } elseif (($perms & 0x8000) === 0x8000) {
            $info = '-';
        } elseif (($perms & 0x6000) === 0x6000) {
            $info = 'b';
        } elseif (($perms & 0x4000) === 0x4000) {
            $info = 'd';
        } elseif (($perms & 0x2000) === 0x2000) {
            $info = 'c';
        } elseif (($perms & 0x1000) === 0x1000) {
            $info = 'p';
        } else {
            $info = 'u';
        }

        $info .= $perms & 0x0100 ? 'r' : '-';
        $info .= $perms & 0x0080 ? 'w' : '-';
        $info .= $perms & 0x0040 ?
                    ($perms & 0x0800 ? 's' : 'x') :
                        ($perms & 0x0800 ? 'S' : '-');

        $info .= $perms & 0x0020 ? 'r' : '-';
        $info .= $perms & 0x0010 ? 'w' : '-';
        $info .= $perms & 0x0008 ?
                    ($perms & 0x0400 ? 's' : 'x') :
                        ($perms & 0x0400 ? 'S' : '-');

        $info .= $perms & 0x0004 ? 'r' : '-';
        $info .= $perms & 0x0002 ? 'w' : '-';
        $info .= $perms & 0x0001 ?
                    ($perms & 0x0200 ? 't' : 'x') :
                        ($perms & 0x0200 ? 'T' : '-');

        return $info;
    }

    /**
     * Get mimetype from file, return `false` if file is invalid
     *
     * @param string $path
     * @return string|bool
     */
    public static function mime($path)
    {
        $mime = false;
        $size = 0;

        if (function_exists('finfo_open')) {
            $buffer = file_get_contents($path, false, null, 0, 5012);

            if ($buffer) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_buffer($finfo, $buffer);
                finfo_close($finfo);

                $size = strlen($buffer);

                $buffer = null;
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if ($mime) {
                $size = filesize($path);
            }
        }

        //Note: $size >= 0 prevents negative numbers for big files (in x86)
        if ($mime !== false && $size >= 0 && $size < 2 && strpos($mime, 'application/') === 0) {
            return 'text/plain';
        }

        return $mime;
    }

    /**
     * Show file in output, if use ob_start is auto used ob_flush. You can set delay in microseconds for cycles
     *
     * @param string $path
     * @param int    $length
     * @param int    $delay
     * @return void|bool
     */
    public static function output($path, $length = 102400, $delay = 0)
    {
        if (false === ($handle = fopen($path, 'rb'))) {
            return false;
        }

        $buffer = ob_get_level() !== 0;

        if (is_int($length) && $length > 0) {
            $length = 102400;
        }

        while (false === feof($handle)) {
            echo fread($handle, $length);

            if ($delay > 0) {
                usleep($delay);
            }

            if ($buffer) {
                ob_flush();
            }

            flush();
        }
    }

    /**
     * Read excerpt from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $maxLen
     * @throws \Inphinit\Exception
     * @return string|bool
     */
    public static function portion($path, $offset = 0, $maxLen = 1024)
    {
        return file_get_contents($path, false, null, $offset, $maxLen);
    }

    /**
     * Read lines from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $maxLine
     * @throws \Inphinit\Exception
     * @return string|bool
     */
    public static function lines($path, $offset = 0, $maxLines = 32)
    {
        if (false === ($handle = fopen($path, 'rb'))) {
            return false;
        }

        $i = 0;
        $output = '';
        $max = $maxLines + $offset - 1;

        while (false === feof($handle)) {
            $data = fgets($handle);

            if ($i >= $offset) {
                $output .= $data;

                if ($i === $max) {
                    break;
                }
            }

            ++$i;
        }

        fclose($handle);

        return $output;
    }
}
