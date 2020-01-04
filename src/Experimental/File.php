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
    /**
     * Read excerpt from a file
     *
     * @param string $path
     * @param int    $offset
     * @param int    $max
     * @throws \Inphinit\Experimental\Exception
     * @return string
     */
    public static function portion($path, $offset = 0, $max = 1024)
    {
        self::fullpath($path);

        return file_get_contents($path, false, null, $offset, $max);
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
    public static function lines($path, $offset = 0, $maxLine = 32)
    {
        $i = 0;
        $output = '';

        $handle = fopen($path, 'rb');

        while (false === feof($handle) && $i < $maxLine) {
            $data = fgets($handle);

            if ($i >= $offset) {
                $output .= $data;
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

        $size = filesize($path);

        if ($size >= 0 && $size < 2) {
            return false;
        }

        $finfo  = finfo_open(FILEINFO_MIME_ENCODING);
        $encode = finfo_buffer($finfo, file_get_contents($path, false, null, 0, 5012));
        finfo_close($finfo);

        return strcasecmp($encode, 'binary') === 0;
    }

    /**
     * Get file size, support for read files with more of 2GB in 32bit.
     * Return `false` if file is not found
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return string|bool
     */
    public static function size($path)
    {
        $path = ltrim(self::fullpath($path), '/');

        $ch = curl_init('file://' . $path);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);

        $headers = curl_exec($ch);

        curl_close($ch);

        $ch = null;

        if (preg_match('#\bcontent-length:(\s+?|)(\d+)#i', $headers, $matches)) {
            return $matches[2];
        }

        return false;
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
