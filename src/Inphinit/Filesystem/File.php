<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Filesystem;

use Inphinit\App;
use Inphinit\Exception;
use Inphinit\Utility\Url;

class File
{
    private static $infos = array();
    private static $devStrictMode = false;

    /**
     * Enable or disable strictmode for check if file exists with case-sensitive (only avaliable in development mode)
     *
     * @param bool $enable
     * @return void
     */
    public static function strictMode($enable)
    {
        self::$devStrictMode = $enable;
    }

    /**
     * Check if file exists using case-sensitive,
     * For help developers who using Windows OS and using unix-like for production
     *
     * @param string $path
     * @return bool
     */
    public static function exists($path)
    {
        // Removing the file URI scheme for compatibility with realpath() function
        if (stripos($path, 'file:') === 0) {
            $path = parse_url($path, PHP_URL_PATH);
        }

        if (realpath($path) === false) {
            return false;
        }

        $path = str_replace('\\', '/', $path);

        // Canonicalize the path for support in the inphinit_check_path() function
        if (strpos($path, './') !== false || strpos($path, '//') !== false) {
            $path = Url::canonpath($path);
        }

        return inphinit_check_path($path);
    }

    /**
     * Get file/folder permissions in a format more readable.
     * Return `false` if file is not found
     *
     * @param string $path
     * @param bool   $full
     * @throws \Inphinit\Exception
     * @return string|false
     */
    public static function permissions($path, $full = false)
    {
        self::checkInDevMode($path);

        $perms = fileperms($path);

        if ($perms === false) {
            return $perms;
        }

        $path = realpath($path);
        $from = $full ? 'symbolic' : 'octal';

        if (isset(self::$infos[$path][$from])) {
            return self::$infos[$path][$from];
        }

        if ($full !== true) {
            return self::$infos[$path][$from] = substr(sprintf('%o', $perms), -4);
        }

        switch ($perms & 0xF000) {
            case 0xC000: // socket
                $info = 's';
                break;

            case 0xA000: // symbolic link
                $info = 'l';
                break;

            case 0x8000: // regular
                $info = 'r';
                break;

            case 0x6000: // block special
                $info = 'b';
                break;

            case 0x4000: // directory
                $info = 'd';
                break;

            case 0x2000: // character special
                $info = 'c';
                break;

            case 0x1000: // FIFO pipe
                $info = 'p';
                break;

            default: // unknown
                $info = 'u';
        }

        // Owner
        $from = $perms & 0x0800;
        $info .= $perms & 0x0100 ? 'r' : '-';
        $info .= $perms & 0x0080 ? 'w' : '-';
        $info .= $perms & 0x0040 ? ($from ? 's' : 'x') : ($from ? 'S' : '-');

        // Group
        $from = $perms & 0x0400;
        $info .= $perms & 0x0020 ? 'r' : '-';
        $info .= $perms & 0x0010 ? 'w' : '-';
        $info .= $perms & 0x0008 ? ($from ? 's' : 'x') : ($from ? 'S' : '-');

        // World
        $from = $perms & 0x0200;
        $info .= $perms & 0x0004 ? 'r' : '-';
        $info .= $perms & 0x0002 ? 'w' : '-';
        $info .= $perms & 0x0001 ? ($from ? 't' : 'x') : ($from ? 'T' : '-');

        return self::$infos[$path][$from] = $info;
    }

    /**
     * Show file in output, if use ob_start is auto used ob_flush. You can set delay in microseconds for cycles
     *
     * @param string $path
     * @param int    $length
     * @param int    $delay
     * @throws \Inphinit\Exception
     * @return bool
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

        return true;
    }

    /**
     * Read excerpt from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $length
     * @throws \Inphinit\Exception
     * @return string|false
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
     * @return string|false
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
     * @return void
     */
    public static function clearstat()
    {
        self::$infos = array();
        clearstatcache();
    }

    private static function checkInDevMode($path, $level = 3)
    {
        if (self::$devStrictMode && App::config('development') && self::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)', 0, $level);
        }
    }
}
