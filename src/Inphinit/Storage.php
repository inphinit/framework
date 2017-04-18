<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Storage
{
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

        if ($path . '/' === self::path() || strpos($path, self::path()) === 0) {
            return $path;
        }

        return self::path() . $path;
    }

    /**
     * Clear old files in a folder from storage path
     *
     * @param string $path
     * @param int    $time
     * @return void
     */
    public static function autoclean($path, $time = 0)
    {
        $path = self::resolve($path);

        if ($path !== false && is_dir($path) && ($dh = opendir($path))) {
            if (is_int($time) === false) {
                $time = App::env('appdata_expires');
            }

            $expires = REQUEST_TIME - $time;
            $path .= '/';

            while (false !== ($file = readdir($dh))) {
                $current = $path . $file;

                if (is_file($current) && filemtime($current) < $expires) {
                    unlink($current);
                }
            }

            closedir($dh);

            $dh = null;
        }
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
        $fullpath = self::resolve($path);

        if ($fullpath === false) {
            return false;
        }

        $fullpath .= '/' . $prefix . base_convert(microtime(true), 10, 36);
        $fullpath .= rand(1, 1000) . $sulfix;

        if (is_file($fullpath) || self::put($fullpath, $data, LOCK_EX) === false) {
            return self::temp($data, $path, $prefix, $sulfix);
        }

        return true;
    }

    /**
     * Create a file in a folder in storage
     *
     * @param string   $path
     * @param string   $data
     * @param int|null $flags
     * @return bool|string
     */
    public static function put($path, $data = null, $flags = null)
    {
        $flags = $flags ? $flags : FILE_APPEND|LOCK_EX;

        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        $data = is_numeric($data) === false && !$data ? '' : $data;

        if (is_file($path) && !$data) {
            return true;
        }

        return self::createFolder(dirname($path)) && file_put_contents($path, $data, $flags) !== false;
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

        return $path && is_file($path) && unlink($path);
    }

    /**
     * Create a folder in storage using 0700 permission (if unix-like)
     *
     * @param string $path
     * @return bool
     */
    public static function createFolder($path)
    {
        $path = self::resolve($path);

        return $path && (is_dir($path) || mkdir($path, 0700, true));
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

        return $path && is_dir($path) && self::rrmdir($path);
    }

    /**
     * Remove recursive folders
     *
     * @param string $path
     * @return bool
     */
    private static function rrmdir($path)
    {
        $path .= '/';

        $files = array_diff(scandir($path), array('..', '.'));

        foreach ($files as $file) {
            $current = $path . $file;

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
