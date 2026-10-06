<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Utility;

use Inphinit\Exception;

class Storage
{
    /**
     * Get the path to a file or directory from the application's storage directory
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function path($path)
    {
        if (
            $path === '' ||
            $path === '..' ||
            is_string($path) === false ||
            strpos($path, '../') === 0 ||
            strpos($path, '/..') !== false
        ) {
            throw new Exception('Invalid path');
        }

        $base = INPHINIT_SYSTEM . '/storage/';
        $path = str_replace('\\', '/', $path);

        $full = $base . $path;

        return $full;
    }

    /**
     * Create the directory in storage recursively if it does not exist.
     * Note: If the permission is not defined, the permission of the system/storage directory will be used.
     *
     * @param string   $path
     * @param int|null $permissions
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function mkdir($path, $permissions = null)
    {
        $full = static::path($path);

        if (is_dir($full)) {
            return true;
        }

        if ($permissions === null) {
            $permissions = fileperms(INPHINIT_SYSTEM . '/storage/');
        }

        if ($permissions !== false && mkdir($full, $permissions & 0777, true)) {
            return true;
        }

        clearstatcache(false, $full);

        return is_dir($full);
    }

    /**
     * Set the modified time of a file located in the storage directory, preserving the access time.
     *
     * @param string    $path
     * @param \DateTime $time
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function modified($path, \DateTime $datetime)
    {
        $update = $datetime->getTimestamp();
        $source = static::path($path);
        $source_time = fileatime($source);

        if ($source_time === false) {
            $source_time = time();
        }

        return touch($source, $update, $source_time);
    }

    /**
     * Set the access time of a file located in the storage directory, preserving the modification time.
     *
     * @param string    $path
     * @param \DateTime $time
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function access($path, \DateTime $datetime)
    {
        $update = $datetime->getTimestamp();
        $source = static::path($path);
        $source_time = filemtime($source);

        if ($source_time === false) {
            $source_time = time();
        }

        return touch($source, $source_time, $update);
    }

    /**
     * Clear contents of storage application directory after specified expires date (or UNIX time),
     * and returns number of deleted files
     *
     * @param string    $directory
     * @param \DateTime $expiresAt
     * @param int       $attempts
     * @param callable  $filter
     * @throws \Inphinit\Exception
     * @return int
     */
    public static function clear($path, \DateTime $expiresAt, $attempts = 100, $filter = null)
    {
        $full = self::path($path);

        if (is_dir($full) === false || ($handle = opendir($full)) === false) {
            throw new Exception('Cannot read directory: ' . $full);
        }

        $expires = $expiresAt->getTimestamp();

        if (is_int($attempts) === false || $attempts < 0) {
            throw new Exception('Attempts expects an integer value greater than zero');
        }

        if ($filter !== null && is_callable($filter) === false) {
            throw new Exception('Filter is not callable');
        }

        $full .= '/';
        $changes = 0;
        $error = null;

        try {
            for ($i = 0; $i < $attempts;) {
                $name = readdir($handle);

                if ($name === false) {
                    break;
                }

                $file = $full . $name;

                // Caution: strpos() skips `.`, `..`, and hidden files
                if (strpos($name, '.') !== 0 && is_file($file)) {
                    $mtime = filemtime($file);

                    if ($mtime === false || $mtime >= $expires) {
                        continue;
                    }

                    if ($filter !== null && $filter($name) !== true) {
                        continue;
                    }

                    ++$i;

                    if (unlink($file)) {
                        ++$changes;
                    }
                }
            }
        } catch (\Exception $ex) {
            $error = $ex;
        }

        closedir($handle);

        if ($error !== null) {
            throw new Exception($error->getMessage(), 0, 2, $error);
        }

        return $changes;
    }
}
