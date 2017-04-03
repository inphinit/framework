<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\App;
use Inphinit\Experimental\Uri;

class Storage
{
    private static $sessionStarted = false;
    private static $defaultPaths = array( 'tmp', 'cache', 'log', 'session' );

    /**
     * Get absolute path from storage location
     *
     * @return string
     */
    public static function path()
    {
        return INPHINIT_PATH . 'storage/';
    }

    /**
     * Convert path to storage path
     *
     * @param string $path
     * @return bool|string
     */
    public static function resolve($path)
    {
        $path = strpos($path, '../') !== false ? Uri::canonicalize($path) : $path;

        if (empty($path)) {
            return false;
        }

        if (strpos($path, self::path()) === 0) {
            return $path;
        }

        return self::path() . $path;
    }

    /**
     * Clear old files in a folder from storage path
     *
     * @param string $path
     * @param int    $time
     * @return bool
     */
    public static function autoclean($path, $time = 0)
    {
        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        $response = true;

        if (is_dir($path) && ($dh = opendir($path))) {
            if (is_int($time) === false) {
                $time = App::env('appdata_expires');
            }

            $expires = REQUEST_TIME - $time;

            while (false !== ($file = readdir($dh))) {
                $current = $path . $file;

                if (is_file($current) && filemtime($current) < $expires && unlink($current) === false) {
                    $response = false;
                }
            }

            closedir($dh);

            $dh = $file = null;

            return $response;
        }

        return false;
    }

    /**
     * Create a tmp in storage/tmp folder
     *
     * @param string $data
     * @param string $path
     * @param string $prefix
     * @param string $sulfix
     * @return bool|string
     */
    public static function temp($data = null, $path = 'tmp', $prefix = '~', $sulfix = '.tmp')
    {
        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        $fullpath = $path . '/' . $prefix . base_convert(microtime(true), 10, 36) . rand(1, 1000) . $sulfix;

        if (is_file($fullpath)) {
            return self::temp($data);
        }

        self::createCommomFolders();

        $handle = fopen($fullpath, 'wb');

        if ($handle === false) {
            return false;
        }

        if ($data !== null) {
            fwrite($handle, $data);
        }

        fclose($handle);

        return $fullpath;
    }

    /**
     * Create a file in a folder in storage
     *
     * @param string $path
     * @param string $data
     * @return bool|string
     */
    public static function put($path, $data = null)
    {
        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        if (is_file($path)) {
            if ($data) {
                return file_put_contents($path, $data, FILE_APPEND|LOCK_EX) !== false;
            }

            return true;
        }

        self::createFolder(dirname($path));

        $tmp = self::temp($data);

        return $tmp !== false ? copy($tmp, $path) : false;
    }

    /**
     * Create a file in log folder in storage
     *
     * @param string $name
     * @param string $data
     * @return bool
     */
    public static function log($name, $data)
    {
        return self::put('log/' . $name, $data);
    }

    /**
     * Delete a file in storage
     *
     * @param string $path
     * @return bool
     */
    public static function remove($path)
    {
        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        return is_file($path) && unlink($path);
    }

    /**
     * Create a folder in storage using 0600 permission (if unix-like)
     *
     * @param string $path
     * @return bool
     */
    public static function createFolder($path)
    {
        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        return is_dir($path) || mkdir($path, 0700, true);
    }

    /**
     * Remove recursive folders in storage folder
     *
     * @param string $path
     * @return bool
     */
    public static function removeFolder($path)
    {
        $path = self::resolve($path);

        return $path && self::rrmdir($path);
    }

    /**
     * Create common folders if they don't exist.
     *
     * @return void
     */
    public static function createCommomFolders()
    {
        $paths = self::$defaultPaths;
        $storage = self::path();

        foreach ($paths as $path) {
            if (is_dir($storage . $path) === false) {
                mkdir($storage . $path, 0700, true);
            }
        }

        $paths = null;
    }

    /**
     * Remove recursive folders
     *
     * @param string $path
     * @return bool
     */
    private static function rrmdir($path)
    {
        $files = array_diff(scandir($path), array('..', '.'));

        foreach ($files as $file) {
            $current = $path . '/' . $file;

            if (is_dir($current)) {
                if (self::rrmdir($current) === false) {
                    return false;
                }
            } elseif (unlink($current) === false) {
                return false;
            }
        }

        return rmdir($path);
    }
}
