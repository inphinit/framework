<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Filesystem;

use Inphinit\App;
use Inphinit\Diagnostics\Inspector;
use Inphinit\Exception;
use Inphinit\Utility\Url;

class File
{
    private static $infos = array();
    private static $strictMode = false;

    /**
     * Enables/disable case-sensitive file and directory existence checks performed by
     * the framework in most methods of the `File` class. If disabled, case sensitivity
     * is determined by the operating system or the underlying file system.
     *
     * @param bool|null $enable
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function strict($enable = null)
    {
        if ($enable !== null && is_bool($enable) === false) {
            $type = Inspector::type($enable);
            throw new Exception("Expects to be bool, {$type} given");
        }

        $previous = self::$strictMode;

        if ($enable !== null) {
            self::$strictMode = $enable;
        }

        return $previous;
    }

    /**
     * Checks whether a file or directory exists using case-sensitive path
     * matching, helping developers maintain consistent behavior across
     * Windows development environments and Unix-like production systems
     * Note: `File::exists()` method is not affected by this configuration
     *
     * @param string $path
     * @return bool
     */
    public static function exists($path)
    {
        if (file_exists($path) === false) {
            return false;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^file:/+([a-z]:|/)#i', '$1', $path);

        // Resolve `/./`, `/../`, and `/` extras before using `inphinit_check_path()`
        if (strpos($path, './') !== false || strpos($path, '//') !== false) {
            $path = Url::canonpath($path);
        }

        // Paths that start with `/` or contain a drive letter (e.g., `D:/`) are likely not relative
        if (strpos($path, '/') !== 0 && strpos($path, ':/') === false) {
            $current_dir = realpath('.');

            // Caution: Returns false if there are no execute permissions for all directories in the hierarchy
            if ($current_dir === false) {
                return false;
            }

            $path = str_replace('\\', '/', $current_dir) . '/' . $path;
        }

        return inphinit_check_path($path);
    }

    /**
     * Get file/directory permissions in a format more readable.
     * Return `false` if file is not found
     *
     * @param string $path File path
     * @param bool   $full If true, it returns the full format; otherwise, it returns the octal format.
     * @param bool   $link If true, it indicates that it should return the permissions of a symbolic link and not the source.
     * @throws \Inphinit\Exception
     * @return string|false
     */
    public static function permissions($path, $full = false, $link = false)
    {
        $cache  = $full ? 'full+' : 'octal+';
        $cache .= $link ? 'link:' : 'file:';
        $cache .= $path;

        if (isset(self::$infos[$cache])) {
            return self::$infos[$cache];
        }

        self::checkCaseSensitive($path);

        if ($link) {
            $stat = lstat($path);
            $perms = $stat ? $stat['mode'] : false;
        } else {
            $perms = fileperms($path);
        }

        if ($perms === false) {
            return false;
        }

        if ($full === false) {
            $info = substr(sprintf('%o', $perms), -4);

            self::$infos[$cache] = $info;

            return $info;
        }

        // note: https://github.com/php/doc-en/pull/5842

        switch ($perms & 0xF000) {
            case 0x1000: $info = 'p'; break; // FIFO pipe
            case 0x2000: $info = 'c'; break; // character special
            case 0x4000: $info = 'd'; break; // directory
            case 0x6000: $info = 'b'; break; // block special
            case 0x8000: $info = '-'; break; // regular
            case 0xA000: $info = 'l'; break; // symbolic link
            case 0xC000: $info = 's'; break; // socket

            // unknown
            default: $info = 'u';
        }

        // Owner
        $setuid = $perms & 0x0800;
        $info .= (($perms & 0x0100) ? 'r' : '-');
        $info .= (($perms & 0x0080) ? 'w' : '-');
        $info .= (($perms & 0x0040) ? ($setuid ? 's' : 'x') : ($setuid ? 'S' : '-'));

        // Group
        $setgid = $perms & 0x0400;
        $info .= (($perms & 0x0020) ? 'r' : '-');
        $info .= (($perms & 0x0010) ? 'w' : '-');
        $info .= (($perms & 0x0008) ? ($setgid ? 's' : 'x') : ($setgid ? 'S' : '-'));

        // Others
        $sticky = $perms & 0x0200;
        $info .= (($perms & 0x0004) ? 'r' : '-');
        $info .= (($perms & 0x0002) ? 'w' : '-');
        $info .= (($perms & 0x0001) ? ($sticky ? 't' : 'x') : ($sticky ? 'T' : '-'));

        self::$infos[$cache] = $info;

        return $info;
    }

    /**
     * Show file in output, if use ob_start is auto used ob_flush
     *
     * @param string $path   File path
     * @param int    $length Up to length number of bytes read
     * @param int    $delay  Sets the interval between each reading
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function output($path, $length = 0, $delay = 0)
    {
        self::checkCaseSensitive($path);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $buffer = ob_get_level() !== 0;

        if ($length < 1) {
            $length = 262144;
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

        fclose($handle);

        return true;
    }

    /**
     * Reads file into a string, starting at the specified offset up to length bytes
     * Note: Same behavior as `file_get_contents`, but case-sensitive in development mode
     *
     * @param string $path   File path
     * @param int    $offset The offset where the reading starts on the original stream (Negative offsets count from the end of the stream)
     * @param int    $length Maximum length of data read. The default is to read until end of file is reached
     * @throws \Inphinit\Exception
     * @return string|false
     */
    public static function portion($path, $offset = 0, $length = 1024)
    {
        self::checkCaseSensitive($path);

        return file_get_contents($path, false, null, $offset, $length);
    }

    /**
     * Read lines from a file
     *
     * @param string $path   File path
     * @param int    $offset Number of rows to skip (`0` disables skips)
     * @param int    $length Maximum number of lines read (`0` disables line limits)
     * @throws \Inphinit\Exception
     * @return array|false
     */
    public static function lines($path, $offset = 0, $length = 100)
    {
        if (is_int($offset) === false || $offset < 0) {
            throw new Exception('Invalid offset');
        }

        if (is_int($length) === false || $length < 0) {
            throw new Exception('Invalid length');
        }

        self::checkCaseSensitive($path);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $index = 0;
        $output = array();
        $last_index = $length === 0 ? -1 : ($offset + $length - 1);

        while (($data = fgets($handle)) !== false) {
            if ($index >= $offset) {
                $output[] = $data;

                if ($index === $last_index) {
                    break;
                }
            }

            ++$index;
        }

        fclose($handle);

        return $output;
    }

    /**
     * Clear state files and clear info files from `Inphinit\File::permissions`
     */
    public static function clearstat()
    {
        self::$infos = array();
        clearstatcache();
    }

    private static function checkCaseSensitive($path, $level = 3)
    {
        if (self::$strictMode && self::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)', 0, $level);
        }
    }
}
