<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

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
        $base = INPHINIT_SYSTEM . '/storage/';
        $path = str_replace('\\', '/', $path);

        $full = $base . $path;

        if ($path === '' || $path === '..' || strpos($path, '../') === 0 || strpos($path, '/..') !== false) {
            throw new Exception('Invalid path level: ' . $full);
        }

        return $full;
    }

    /**
     * Updates only the modification date of a file in the application's storage directory and preserves the access date.
     * Note: If file not exists, it is created
     *
     * @param string        $path
     * @param int|\DateTime $modified
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function modified($path, $modified)
    {
        $modified = self::getUnixTimestamp($modified, 'Invalid modified');
        $current = static::path($path);
        $access = fileatime($current);

        return $access !== false && touch($current, $modified, $access);
    }

    /**
     * Updates only the access date of a file in the application's storage directory and preserves the modification date.
     * Note: If file not exists, it is created
     *
     * @param string        $path
     * @param int|\DateTime $modified
     * @throws \Inphinit\Exception
     * @return bool
     */
    public static function access($path, $access)
    {
        $access = self::getUnixTimestamp($access, 'Invalid modified');
        $current = static::path($path);
        $modified = filemtime($current);

        return $modified !== false && touch($current, $modified, $access);
    }

    /**
     * Clear contents of storage application directory after specified expires date, and returns number of deleted files
     *
     * @param string        $directory
     * @param int|\DateTime $expires
     * @param int           $attempts
     * @param callable      $filter
     * @throws \Inphinit\Exception
     * @return int
     */
    public static function clear($path, $expires, $attempts = 100, $filter = null)
    {
        if ($filter !== null && is_callable($filter) === false) {
            throw new Exception('Filter is not callable');
        }

        $full = self::path($path);

        $expires = self::getUnixTimestamp($expires, 'Invalid expires');

        if (is_dir($full) === false || ($handle = opendir($full)) === false) {
            throw new Exception('Cannot read directory: ' . $full);
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
                if (strpos($name, '.') !== 0 && is_file($file) && filemtime($file) < $expires) {
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

    private static function getUnixTimestamp($date, $message)
    {
        if ($date instanceof \DateTime) {
            $date = $date->getTimestamp();
        }

        if (is_int($date) === false || $date < 0) {
            throw new Exception($message, 0, 3);
        }

        return $date;
    }
}
