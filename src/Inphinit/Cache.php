<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Http\Request;
use Inphinit\Http\Response;

class Cache
{
    private $handle;
    private $cacheName;
    private $cacheTmp;
    private $isCache = false;
    private $noStarted = true;
    private $expires;
    private $modified;
    private $finished = false;
    private static $needHeaders;

    /**
     * Create a cache instance by route path
     *
     * @param int    $expires
     * @param int    $modified
     * @param string $prefix
     * @param bool   $querystring
     * @return void
     */
    public function __construct($expires = 900, $modified = 0, $prefix = '', $querystring = false)
    {
        $filename = INPHINIT_PATH . 'storage/cache/output/';

        $path = \UtilsPath();

        $filename .= strlen($path) . '/' . sha1($path) . '/';

        $name = '';

        if (isset($prefix[0])) {
            $name = strlen($prefix) . '.' . sha1($prefix) . '/';
        }

        if ($querystring && ($qs = Request::query())) {
            $name .= strlen($qs) . '.' . sha1($qs);
        } else {
            $name .= 'cache';
        }

        $filename .= $name;
        $checkexpires = $filename . '.1';

        $this->cacheName = $filename;

        if (is_file($filename) && is_file($checkexpires)) {
            $this->isCache = file_get_contents($checkexpires) > REQUEST_TIME;

            if ($this->isCache && static::allowHeaders()) {
                $etag = sha1_file($filename);

                if (self::match(REQUEST_TIME + $modified, $etag)) {
                    Response::putHeader('Etag', $etag);
                    Response::cache($expires, $modified);
                    Response::dispatch();
                    App::stop(304);
                }
            }

            if ($this->isCache) {
                App::on('ready', array($this, 'show'));

                return null;
            }
        }

        $this->cacheTmp = Storage::temp();

        $tmp = fopen($this->cacheTmp, 'wb');

        if ($tmp === false) {
            return null;
        }

        $this->handle = $tmp;
        $this->expires = $expires;
        $this->modified = $modified === 0 ? REQUEST_TIME : $modified;

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (ob_start(array($this, 'write'), 1024)) {
            $this->noStarted = false;
            App::on('finish', array($this, 'finish'));
            App::on('error', array($this, 'finish'));
        }
    }

    /**
     * Check if is HEAD or GET, you can overwrite this method
     *
     * @return bool
     */
    protected static function allowHeaders()
    {
        if (self::$needHeaders !== null) {
            return self::$needHeaders;
        }

        return self::$needHeaders = Request::is('GET') || Request::is('HEAD');
    }

    /**
     * Write cache
     *
     * @return void
     */
    public function finish()
    {
        if ($this->isCache || $this->noStarted || $this->finished) {
            return null;
        }

        $this->finished = true;

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if ($this->handle) {
            fclose($this->handle);
        }

        if (App::state() > 3) {
            if (is_file($this->cacheTmp)) {
                unlink($this->cacheTmp);
            }

            return null;
        }

        Storage::createFolder(dirname($this->cacheName));

        Storage::put($this->cacheName);

        if (filesize($this->cacheTmp) > 0 && rename($this->cacheTmp, $this->cacheName)) {
            $headers = implode('\');header(\'', headers_list());

            if ($headers !== '') {
                $headers = 'header(\'' . $headers . '\');';
            }

            file_put_contents($this->cacheName . '.1', REQUEST_TIME + $this->expires);
            file_put_contents($this->cacheName . '.php', '<?php ' . $headers);

            if (static::allowHeaders()) {
                Response::putHeader('Etag', sha1_file($this->cacheName));
                Response::cache($this->expires, $this->modified);
                Response::dispatch();
            }

            if (App::state() > 2) {
                $this->show();
            } else {
                App::on('ready', array($this, 'show'));
            }
        }
    }

    /**
     * Check `HTTP_IF_MODIFIED_SINCE` and `HTTP_IF_NONE_MATCH` from server
     * If true you can send `304 Not Modified`
     *
     * @param string $modified
     * @param string $etag
     * @return bool
     */
    public static function match($modified, $etag = null)
    {
        $modifiedsince = Request::header('If-Modified-Since');

        if ($modifiedsince && strtotime($modifiedsince) == $modified) {
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
        if ($this->handle) {
            fwrite($this->handle, $data);
        }

        return '';
    }

    /**
     * Show cache content from current page (from route) in output
     *
     * @return void
     */
    public function show()
    {
        include $this->cacheName . '.php';

        if (filesize($this->cacheName) > 524287) {
            File::output($this->cacheName);
        } else {
            readfile($this->cacheName);
        }
    }
}
