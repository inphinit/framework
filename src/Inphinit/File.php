<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class File
{
    public static function existsCaseSensitive($path)
    {
        return file_exists($path) && \UtilsCaseSensitivePath($path);
    }

    public static function size($path)
    {
        if (file_exists($path) === false) {
            return false;
        }

        $size = filesize($path);
        return $size === false ? false : sprintf('%u', $size);
    }

    public static function permission($path, $full = false)
    {
        if (file_exists($path) === false) {
            return false;
        }

        $perms = fileperms($path);

        if ($full === true) {
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

        return substr(sprintf('%o', $perms), -4);
    }

    public static function mime($path)
    {
        $mimetype = false;

        if (is_readable($path)) {
            if (function_exists('finfo_open')) {
                $finfo    = finfo_open(FILEINFO_MIME_TYPE);
                $mimetype = finfo_buffer($finfo,
                                file_get_contents($path, false, null, -1, 5012),
                                    FILEINFO_MIME_TYPE);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $mimetype = mime_content_type($path);
            }
        } else {
            return $mimetype;
        }

        if ($mimetype !== false && filesize($path) < 2 && strpos($mimetype, 'application/') === 0) {
            $mimetype = 'text/plain';
        }

        return $mimetype;
    }

    public static function output($path, $length = 1024, $delay = 0)
    {
        if (is_readable($path) === false) {
            return false;
        }

        $buffer = ob_get_level() !== 0;

        $handle = fopen($path, 'rb');

        $length = is_int($length) && $length > 0 ? $length : 1024;

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
}
