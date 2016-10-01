<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Session
{
    private $started = false;

    public function __construct()
    {
        $this->started = session_id() !== '';

        if (false === $this->started) {
            AppData::createCommomFolders();

            $this->path(AppData::storagePath() . '/session');
            $this->name('inphinit');

            session_start();

            $this->started = true;
        }
    }

    public function path($path = null)
    {
        return $path === null ? session_save_path() : session_save_path($path);
    }

    public function name($name = null)
    {
        return $name === null ? session_name() : session_name($name);
    }

    public function close()
    {
        if ($this->started) {
            session_write_close();
        }
    }

    public static function get($key, $alternative = false)
    {
        $data = Helper::arrayPath($key, $_SESSION);
        return $data === false ? $alternative : $data;
    }
}
