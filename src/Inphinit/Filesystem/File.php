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
    private static $devStrictMode = true;

    /**
     * Enable or disable strict mode for check if file exists with case-sensitive (available only in development mode)
     *
     * @param bool $enable
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
        $path = str_replace('\\', '/', $path);

        // Canonicalize the path for support in the inphinit_check_path() function
        if (strpos($path, './') !== false || strpos($path, '//') !== false) {
            $path = Url::canonpath($path);
        }

        if (strpos($path, '/') !== 0 && preg_match('#^[a-zA-Z]+?\:#', $path) !== 1) {
            $path = str_replace('\\', '/', getcwd()) . '/' . $path;
        }

        return inphinit_check_path($path);
    }

    /**
     * Get file/folder permissions in a format more readable.
     * Return `false` if file is not found
     *
     * @param string $path
     * @param bool   $symbolic
     * @throws \Inphinit\Exception
     * @return string|false
     */
    public static function permissions($path, $symbolic = false)
    {
        self::checkInDevMode($path);

        $perms = fileperms($path);

        if ($perms === false) {
            return false;
        }

        $type = $symbolic ? 'symbolic' : 'octal';

        if (isset(self::$infos[$path][$type])) {
            return self::$infos[$path][$type];
        }

        if ($symbolic !== true) {
            return self::$infos[$path][$type] = substr(sprintf('%o', $perms), -4);
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

        return self::$infos[$path][$type] = $info;
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
    public static function output($path, $length = 0, $delay = 0)
    {
        self::checkInDevMode($path);

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $buffer = ob_get_level() !== 0;

        if ($length === null || $length < 1) {
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

        if ($handle === false) {
            return false;
        }

        $i = 0;
        $output = '';
        $max = $max + $offset - 1;

        while (($data = fgets($handle)) !== false) {
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

    /**
     * Clear state files and clear info files from `Inphinit\File::permissions`
     */
    public static function clearstat()
    {
        self::$infos = array();
        clearstatcache();
    }

    private static function checkInDevMode($path, $level = 3)
    {
        if (self::$devStrictMode && App::config('environment') === 'development' && self::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)', 0, $level);
        }
    }
}
