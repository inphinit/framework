<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class File extends \Inphinit\File
{
    private static $sizes = array();
    private static $bin = array();

    /**
     * Read excerpt from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $max
     * @throws \Inphinit\Experimental\Exception
     * @return string
     */
    public static function portion($path, $offset = 0, $maxLen = 1024)
    {
        self::fullpath($path);

        return file_get_contents($path, false, null, $offset, $maxLen);
    }

    /**
     * Read lines from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $maxLine
     * @throws \Inphinit\Experimental\Exception
     * @return string
     */
    public static function lines($path, $offset = 0, $maxLines = 32)
    {
        self::fullpath($path);

        $i = 0;
        $output = '';
        $max = $maxLines + $offset - 1;

        $handle = fopen($path, 'rb');

        while (false === feof($handle)) {
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

    /**
     * Determines whether the file is binary
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return bool
     */
    public static function isBinary($path)
    {
        self::fullpath($path);

        if (isset(self::$bin[$path]) === false) {
            $finfo = finfo_open(FILEINFO_MIME_ENCODING);
            $encode = finfo_buffer($finfo, file_get_contents($path, false, null, 0, 5012));
            finfo_close($finfo);

            self::$bin[$path] = strcasecmp($encode, 'binary') === 0;
        }

        return self::$bin[$path];
    }

    /**
     * Get file size, support for read files with more of 2GB in 32bit.
     * Return `false` if file is not found
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return float|bool
     */
    public static function size($path)
    {
        $path = self::fullpath($path);

        if (isset(self::$sizes[$path]) === false) {
            $ch = curl_init('file://' . rawurlencode($path));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);

            if (curl_exec($ch) !== false) {
                $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            } else {
                $size = -1;
            }

            curl_close($ch);

            if ($size > -1) {
                self::$sizes[$path] = $size;
            } else {
                self::$sizes[$path] = false;
            }
        }

        return self::$sizes[$path];
    }

    /**
     * Clear state files and clear size files in `Inphini\Experimental\File::size`
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return string|bool
     */
    public static function clearstat()
    {
        self::$sizes = array();

        clearstatcache();
    }

    private static function fullpath($path)
    {
        if (false === self::exists($path) || false === is_file($path)) {
            throw new Exception($path . ' not found', 3);
        } elseif (false === is_readable($path)) {
            throw new Exception($path . ' not readable', 3);
        }

        return realpath($path);
    }
}
