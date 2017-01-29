<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

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
     * Clear old files in a folder from stoage path
     *
     * @param string $path
     * @param int    $time
     * @return bool
     */
    public static function autoclean($path, $time = 0)
    {
        if (in_array($path, self::$defaultPaths) === false) {
            return false;
        }

        $dir = self::path() . $path . '/';
        $response = true;

        if (is_dir($dir) && ($dh = opendir($dir))) {
            if (is_int($time) === false) {
                $time = 86400;
            }

            $expires = REQUEST_TIME - $time;

            while (false !== ($file = readdir($dh))) {
                $path = $dir . $file;

                if (is_file($path) && filemtime($path) < $expires && unlink($path) === false) {
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
        if (false === in_array($path, self::$defaultPaths)) {
            return false;
        }

        $fullpath = self::path() . $path . '/' . $prefix .
                        base_convert(microtime(false), 10, 36) . rand(1, 1000) . $sulfix;

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
                file_put_contents($path, $data);
            }

            return $path;
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

        if (is_file($path) && unlink($path)) {
            return true;
        }

        return false;
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

        if (is_dir($path)) {
            return true;
        }

        $mask = umask(0);

        $r = mkdir($path, 0600, true);

        umask($mask);

        return $r;
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

        if ($path === false) {
            return false;
        }

        return self::rrmdir($path);
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

        $mask = umask(0);

        foreach ($paths as $path) {
            if (is_dir($storage . $path) === false) {
                mkdir($storage . $path, 0600);
            }
        }

        umask($mask);

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