<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Cache
{
    private $expires;
    private $handle;
    private $cacheName;
    private $cacheTmp;
    private $isCache = false;

    public function __construct($expires = 900, $lastModified = 0, $prefix = '')
    {
        $this->expires = $expires;

        if (AppData::createFolder('cache/output') === false) {
            return null;
        }

        $this->lastModified = $lastModified === 0 ? (REQUEST_TIME + $this->expires) : $lastModified;

        $filename  = INPHINIT_PATH . 'storage/cache/output/~';

        if (false === empty($prefix)) {
            $filename .= strlen($prefix) . '.' . sha1($prefix) . '_';
        }

        $path = \UtilsPath();

        $filename .= sha1($path) . '-' . strlen($path);
        $lastModify = $filename . '.1';

        $this->cacheName = $filename;

        if (file_exists($filename) && file_exists($lastModify)) {
            $data = file_get_contents($lastModify);

            if ($data !== false && $data > REQUEST_TIME) {
                $this->isCache      = true;

                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $data) . ' GMT');
                header('Etag: ' . sha1_file($filename));

                if (self::match($data)) {
                    Response::status(304);
                } else {
                    App::on('ready', array($this, 'show'));
                }

                return null;
            }
        }

        $this->cacheTmp = AppData::createTmp();

        $tmp = fopen($this->cacheTmp, 'wb');

        if ($tmp === false) {
            return null;
        }

        $this->handle = $tmp;

        App::on('ready', array($this, 'finish'));

        App::buffer(array($this, 'write'), 1024);
    }

    public function finish()
    {
        if ($this->isCache) {
            return null;
        }

        if ($this->handle) {
            ob_end_flush();
            fclose($this->handle);
        }

        if (App::hasError()) {
            return null;
        }

        if (File::size($this->cacheTmp) > 0) {
            copy($this->cacheTmp, $this->cacheName);
            file_put_contents($this->cacheName . '.1', $this->lastModified);
        }
    }

    public static function match($lm)
    {
        $nm = false;

        if (false === empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            if (preg_match('/^[a-z]{3}[,] \d{2} [a-z]{3} \d{4} \d{2}[:]\d{2}[:]\d{2} GMT$/i', $_SERVER['HTTP_IF_MODIFIED_SINCE']) !== 0 &&
                strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $lm)
            {
                $nm = true;
            }
        }

        if ($nm === false && false === empty($_SERVER['HTTP_IF_NONE_MATCH']) &&
                $lm === trim($_SERVER['HTTP_IF_NONE_MATCH']))
        {
            $nm = true;
        }

        return $nm;
    }

    public function cached()
    {
        return $this->isCache;
    }

    public function write($data)
    {
        if ($this->handle !== null) {
            fwrite($this->handle, $data);
        }

        return $data;
    }

    public function show()
    {
        File::output($this->cacheName, 1024);
    }
}
