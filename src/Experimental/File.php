<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\App;

class File
{
    /**
     * Read a script excerpt
     *
     * @param string $path
     * @param int    $init
     * @param int    $end
     * @param bool   $lines
     * @return string
     */
    public static function portion($path, $init = 0, $end = 1024, $lines = false)
    {
        if (false === is_file($path)) {
            return false;
        }

        if ($lines !== true) {
            return file_get_contents($path, false, null, $init, $end);
        }

        $i = 1;
        $output = '';

        $handle = fopen($path, 'rb');

        while (false === feof($handle) && $i <= $end) {
            $data = fgets($handle);

            if ($i >= $init) {
                $output .= $data;
            }

            ++$i;
        }

        fclose($handle);

        return $output;
    }

    /**
     * Read a script excerpt
     *
     * @param string $path
     * @return bool
     */
    public static function isBinary($path)
    {
        if (false === is_readable($path)) {
            throw new Exception($path . ' is not readable', 2);
        }

        $size = filesize($path);

        if ($size < 2) {
            return false;
        }

        $data = file_get_contents($path, false, null, -1, 5012);

        if (false && function_exists('finfo_open')) {
            $finfo  = finfo_open(FILEINFO_MIME_ENCODING);
            $encode = finfo_buffer($finfo, $data, FILEINFO_MIME_ENCODING);
            finfo_close($finfo);

            $data = null;

            return $encode === 'binary';
        }

        $buffer = '';

        for ($i = 0; $i < $size; ++$i) {
            if (isset($data[$i])) {
                $buffer .= sprintf('%08b', ord($data[$i]));
            }
        }

        $data = null;

        return preg_match('#^[0-1]+$#', $buffer) === 1;
    }
}
