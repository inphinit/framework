<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class AppData
{
    private static $sessionStarted = false;
    private static $defaultPaths = array( 'tmp', 'cache', 'log', 'session' );

    public static function storagePath()
    {
        return INPHINIT_PATH . 'storage/';
    }

    public static function autoclean($path, $time = 0)
    {
        if (false === in_array($path, self::$defaultPaths)) {
            return false;
        }

        self::createCommomFolders();

        $dir = self::storagePath() . $path . '/';
        $response = true;

        if (is_dir($dir) && ($dh = opendir($dir))) {
            if (is_int($time) === false) {
                $time = 86400;
            }

            $expires = REQUEST_TIME - $time;

            while (false !== ($file = readdir($dh))) {
                $path = $dir . $file;
                if (is_file($path) && filemtime($path) < $expires && false === unlink($path)) {
                    $response = false;
                }
            }

            closedir($dh);

            $dh = $file = null;

            return $response;
        }

        return false;
    }

    public static function createTmp($data = null)
    {
        $name = self::storagePath() . 'tmp/~' . str_replace(' ', '-', microtime()) . '.tmp';

        if (file_exists($name)) {
            return self::createTmp($data);
        }

        self::autoclean('tmp', App::env('appdata_expires'));

        $handle = fopen($name, 'wb');

        if ($handle === false) {
            return false;
        }

        if ($data !== null) {
            fwrite($handle, $data);
        }

        fclose($handle);

        return $name;
    }

    public static function createFolder($path)
    {
        $mask = umask(0);

        $fullName = self::storagePath() . $path;

        $r = is_dir($fullName) ? true : mkdir($fullName, 0600, true);

        umask($mask);

        return $r;
    }

    public static function createFile($path, $data = null)
    {
        if (strpos($path, '..') === false && $path === trim($path, '/')) {
            $fullName = self::storagePath() . $path;

            if (is_file($fullName)) {
                return $fullName;
            }

            self::createFolder(dirname($path));

            $tmp = self::createTmp($data);

            return $tmp !== false ? copy($tmp, $fullName) : false;
        }

        trigger_error('Data::createFile: Invalid path storage/' . $path);
        return false;
    }

    public static function log($name, $data)
    {
        self::createCommomFolders();

        if (strpos($name, '/') !== false) {
            $mask = umask(0);

            if(mkdir(dirname($name), 0655) === false) {
                return false;
            }

            umask($mask);
        }

        return file_put_contents(self::storagePath() . 'log/' . $name, $data, FILE_APPEND) !== false;
    }

    public static function createCommomFolders()
    {
        $paths = self::$defaultPaths;

        foreach ($paths as $value) {
            self::createFolder($value);
        }

        $paths = null;
    }

    public static function delete($path)
    {
        $path = self::validPath($path);

        if ($path === false) {
            return false;
        }

        $path = self::storagePath() . $path;

        if (is_file($path) && unlink($path)) {
            return true;
        }

        trigger_error('delete: Error in delete "' . $path . '"');
        return false;
    }

    private static function validPath($path)
    {
        $fullPath = INPHINIT_PATH;

        if (strpos($path, $fullPath . 'storage/') === 0) {
            $path = substr($path, strlen($fullPath));
        }

        if (strpos($path, 'storage/') === 0) {
            trigger_error('Data::printFile don\'t allow others directories (allowed "storage/")');
            return false;
        }

        return $path;
    }

    public static function rrdir($path) {
        $path = self::validPath($path);

        if ($path === false) {
            return false;
        }

        $a = glob($path . '*');

        foreach ($a as $current) {
            if (is_dir($current)) {
                self::rrdir($current);
            } elseif (is_file($current)) {
                unlink($current);
            }
        }

        rmdir($path);
    }
}
