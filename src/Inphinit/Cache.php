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
    private $expires;
    private $lastModified;

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
        $filename = Storage::resolve('cache/output/') . '~';

        if (false === empty($prefix)) {
            $filename .= strlen($prefix) . '.' . sha1($prefix) . '_';
        }

        $path = \UtilsPath();

        $filename .= sha1($path) . '-' . strlen($path);
        $lastModify = $filename . '.1';

        $this->cacheName = $filename;

        if (is_file($filename) && is_file($lastModify)) {
            $lmdata = file_get_contents($lastModify);

            $this->isCache = $lmdata > REQUEST_TIME;

            if ($this->isCache && (Request::is('GET') || Request::is('HEAD'))) {
                $etag = sha1_file($filename);

                header('Etag: ' . $etag);

                Response::cache($expires, $lmdata);

                if (self::match($lmdata, $etag)) {
                    App::stop(304);
                }
            }

            if ($this->isCache) {
                App::on('ready', array($this, 'show'));

                return null;
            }
        }

        if (Storage::createFolder('cache/output') === false) {
            return null;
        }

        $this->cacheTmp = Storage::temp();

        $tmp = fopen($this->cacheTmp, 'wb');

        if ($tmp === false) {
            return null;
        }

        $this->handle = $tmp;
        $this->expires = $expires;
        $this->lastModified = $lastModified === 0 ? (REQUEST_TIME + $expires) : $lastModified;

        App::on('finish', array($this, 'finish'));

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        ob_start(array($this, 'write'), 1024);
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

        ob_end_flush();

        if (App::hasError()) {
            return null;
        }

        if ($this->handle) {
            fclose($this->handle);
        }

        if (filesize($this->cacheTmp) > 0) {
            copy($this->cacheTmp, $this->cacheName);
            file_put_contents($this->cacheName . '.1', $this->lastModified);
        }
    }

    /**
     * Check `HTTP_IF_MODIFIED_SINCE` and `HTTP_IF_NONE_MATCH` from server
     * If true you can send `304 Not Modified`
     *
     * @param string $lastModified
     * @param string $etag
     * @return bool
     */
    public static function match($lastModified, $etag = null)
    {
        $modifiedsince = Request::header('If-Modified-Since');

        if ($modifiedsince &&
            preg_match('/^[a-z]{3}[,] \d{2} [a-z]{3} \d{4} \d{2}[:]\d{2}[:]\d{2} GMT$/i', $modifiedsince) !== 0 &&
            strtotime($modifiedsince) == $lastModified) {
            return true;
        }

        $nonematch = Request::header('If-None-Match');

        return $nonematch && trim($nonematch) === $etag;
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
