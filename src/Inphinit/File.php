<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Uri;

class File
{
    private static $infos = array();
    private static $handleFinfo;

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

        if (strpos($path, './') !== false || strpos($path, '//') !== false) {
            $path = Uri::canonpath($path);
        }

        return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path) === realpath($path);
    }

    /**
     * Get file/folder permissions in a format more readable.
     * Return `false` if file is not found
     *
     * @param string $path
     * @param bool   $full
     * @return string|bool
     */
    public static function permissions($path, $full = false)
    {
        self::checkInDevMode($path);

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
        $info = self::info($path);
        return $info ? strtok($info, ';') : false;
    }

    /**
     * Determines whether the file is binary
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function encoding($path)
    {
        $info = self::info($path);

        if ($info) {
            $pos = strpos($info, ';');

            if ($pos === false) {
                $info = false;
            } else {
                $info = explode('charset=', $info);
                return $info[1];
            }
        }

        return $info;
    }

    private static function info($path)
    {
        self::checkInDevMode($path);

        if (isset(self::$infos[$path]) === false && $buffer = file_get_contents($path, false, null, 0, 5012)) {
            if (self::$handleFinfo === null) {
                self::$handleFinfo = finfo_open(FILEINFO_MIME);
            }

            self::$infos[$path] = finfo_buffer(self::$handleFinfo, $buffer);
        }

        return self::$infos[$path];
    }

    /**
     * Show file in output, if use ob_start is auto used ob_flush. You can set delay in microseconds for cycles
     *
     * @param string $path
     * @param int    $length
     * @param int    $delay
     * @return void|bool
     */
    public static function output($path, $length = 262144, $delay = 0)
    {
        self::checkInDevMode($path);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $buffer = ob_get_level() !== 0;

        if (is_int($length) && $length > 0) {
            $length = 102400;
        }

        while (feof($handle) === false) {
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
     * @param int    $length
     * @throws \Inphinit\Exception
     * @return string|bool
     */
    public static function portion($path, $offset = 0, $length = 1024)
    {
        self::checkInDevMode($path);

        return file_get_contents($path, false, null, $offset, $length);
    }

    /**
     * Read lines from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $max
     * @throws \Inphinit\Exception
     * @return string|bool
     */
    public static function lines($path, $offset = 0, $max = 32)
    {
        self::checkInDevMode($path);

        $handle = fopen($path, 'rb');

        if ($handle) {
            $i = 0;
            $output = '';
            $max = $max + $offset - 1;

            while (feof($handle) === false) {
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

        return false;
    }

    /**
     * Clear state files and clear size files in `Inphinit\File::size`
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return void
     */
    public static function clearstat()
    {
        self::$infos = array();

        finfo_close(self::$handleFinfo);

        self::$handleFinfo = null;

        clearstatcache();
    }

    private static function checkInDevMode($path)
    {
        if (App::config('development') && self::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)', 0, 3);
        }
    }
}
