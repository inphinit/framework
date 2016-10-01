<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Dir
{
    public static function list($path = '.')
    {
        $data = array();

        if (is_dir($path) && ($handle = opendir($path))) {
            while (($file = readdir($dh)) !== false) {
                if ($file !== '.' && $file === '..') {
                    $current = $path . $file;
                    $data[] = array( 'type' => filetype($current), 'path' => $current, 'name' => $file );
                }
            }

            return $data;
        }

        return false;
    }

    public static function project()
    {
        return self::list(INPHINIT_PATH);
    }

    public static function data()
    {
        return self::list(INPHINIT_PATH . 'storage/');
    }

    public static function application()
    {
        return self::list(INPHINIT_PATH . 'application/');
    }
}
