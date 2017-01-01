<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Cache
{
    private $handle;
    private $cacheName;
    private $cacheTmp;
    private $isCache = false;

    /**
     * Create a cache instance by route path
     *
     * @param int    $expires
     * @param int    $lastModified
     * @param string $prefix
     * @return void
     */
    public function __construct($expires = 900, $lastModified = 0, $prefix = '')
    {
        if (Storage::createFolder('cache/output') === false) {
            return null;
        }

        $this->lastModified = $lastModified === 0 ? (REQUEST_TIME + $expires) : $lastModified;

        $filename = INPHINIT_PATH . 'storage/cache/output/~';

        if (false === empty($prefix)) {
            $filename .= strlen($prefix) . '.' . sha1($prefix) . '_';
        }

        $path = \UtilsPath();

        $filename .= sha1($path) . '-' . strlen($path);
        $lastModify = $filename . '.1';

        $this->cacheName = $filename;

        if (file_exists($filename) && file_exists($lastModify)) {
            $data = (int) file_get_contents($lastModify);

            if ($data !== false && $data > REQUEST_TIME) {
                $this->isCache = true;

                header('Etag: ' . sha1_file($filename));

                Response::cache($expires, $data);

                if (self::match($data)) {
                    Response::status(304);
                } else {
                    App::on('ready', array($this, 'show'));
                }

                return null;
            }
        }

        $this->cacheTmp = Storage::temp();

        $tmp = fopen($this->cacheTmp, 'wb');

        if ($tmp === false) {
            return null;
        }

        $this->handle = $tmp;

        App::on('finish', array($this, 'finish'));

        App::buffer(array($this, 'write'), 1024);
    }

    /**
     * Write cache
     *
     * @return void
     */
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

    /**
     * Check `HTTP_IF_MODIFIED_SINCE`,` HTTP_IF_MODIFIED_SINCE` and `HTTP_IF_NONE_MATCH` from server
     * If true you can send `304 Not Modified`
     *
     * @param string $lm
     * @return bool
     */
    public static function match($lastModified)
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

    /**
     * Checks if page (from route) is already cached.
     *
     * @return bool
     */
    public function cached()
    {
        return $this->isCache;
    }

    /**
     * Write data in cache file.
     * This method returns the set value itself because the class uses `ob_start`
     *
     * @param string $data
     * @return string
     */
    public function write($data)
    {
        if ($this->handle !== null) {
            fwrite($this->handle, $data);
        }

        return $data;
    }

    /**
     * Show cache content from current page (from route) in output
     *
     * @return void
     */
    public function show()
    {
        if (filesize($this->cacheName) > 524287) {
            File::output($this->cacheName);
        } else {
            readfile($this->cacheName);
        }
    }
}
