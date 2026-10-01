<?php
/**
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\App;
use Inphinit\Event;
use Inphinit\Exception;
use Inphinit\Filesystem\File;
use Inphinit\Http\Request;
use Inphinit\Http\Response;

class Cache
{
    /** @var int Returned by `start()` when a fresh cached response was served */
    const CACHED = 1;

    /** @var int Returned by `start()` when the cache could not be created (e.g. storage not writable) */
    const FAILED = 2;

    /** @var int Returned by `start()` when the current response started being recorded to the cache */
    const WRITING = 3;

    private $cache;
    private $cacheTemp;
    private $storage;

    private $debug = false;
    private $handle;
    private $hash;
    private $method;
    private $noErrors = true;
    private $started = false;
    private $writerCallback;

    /*
     * Creates a Cache instance
     *
     * @param string      $method
     * @param string      $path
     * @param string|null $storage
     */
    public function __construct($method = null, $path = null, $storage = null)
    {
        if (App::config('environment') === 'development') {
            $this->debug = true;
        }

        if ($path === null) {
            $path = INPHINIT_PATH;

            if (isset($_SERVER['QUERY_STRING'])) {
                $path .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        $this->hash = static::createHash($path);

        if ($method === null) {
            $method = $_SERVER['REQUEST_METHOD'];
        }

        $this->method = strtoupper($method);

        if ($storage === null) {
            $storage = 'storage/cache/output';
        } else {
            $storage = ltrim($storage, '/');
        }

        $this->storage = $storage;
    }

    public function __destruct()
    {
        if ($this->noErrors && $this->handle && ($response = ob_get_contents())) {
            $this->write($response, 0);
        }

        $this->finish($this->noErrors);
    }

    /*
     * Set a method to overwrite the buffer (can be used for sanitization)
     *
     * @param callable $callback
     */
    public function setWriter(callable $callback)
    {
        $this->writerCallback = $callback;
    }

    /**
     * Starts recording the buffer to the cache, or serves it directly if a fresh copy exists.
     *
     * - Returns `CACHED` and serves the stored response if a fresh cache exists
     * - Returns `WRITING` if recording of the current response just started
     * - Returns `FAILED` if the method/cache is not writable, or the cache could not be created
     *
     * @param int $expires
     * @param int $bufferSize
     * @throws \ErrorException
     * @return int
     */
    public function start($expires = 3600, $bufferSize = 1024)
    {
        if ($this->started) {
            throw new Exception('The cache has already been started');
        }

        $this->started = true;

        if (static::valid($this->method) === false) {
            return $this->debugWithHeader(self::FAILED);
        }

        $hash = $this->hash;
        $time = time();

        $cache = INPHINIT_SYSTEM . '/' . $this->storage . '/' . $hash;

        if (is_file($cache) && ($modified = filemtime($cache)) > ($time - $expires)) {
            $this->debugWithHeader(self::CACHED);

            Response::cache($expires, $modified);
            header('Etag: "' . $hash . '"');

            if (static::match($modified, $hash)) {
                Response::status(304);
            } elseif ($this->method !== 'HEAD') {
                File::output($cache);
            }

            return self::CACHED;
        }

        if (ob_get_level() > 0) {
            ob_end_flush();
        }

        if (headers_sent($file, $line)) {
            throw new \ErrorException('Headers already sent', 0, E_ERROR, $file, $line);
        }

        $cache_temp = $cache . '.tmp';

        $handle = fopen($cache_temp, 'cb');

        if ($handle !== false) {
            $this->handle = $handle;

            // Caution: If the flock fails, it is likely because another request is writing to the cache
            if (
                flock($handle, LOCK_EX | LOCK_NB) &&
                ftruncate($this->handle, 0) &&
                ob_start(array($this, 'write'), $bufferSize, PHP_OUTPUT_HANDLER_FLUSHABLE)
            ) {

                $this->cache = $cache;
                $this->cacheTemp = $cache_temp;

                $error = array($this, 'error');

                Event::on('error', function ($type, $message, $file, $line) use ($error) {
                    $error();
                });

                Response::cache($expires, $time);

                header('Etag: "' . $hash . '"');

                return $this->debugWithHeader(self::WRITING);
            }

            $this->finish(false);
        }

        return $this->debugWithHeader(self::FAILED);
    }

    /**
     * Check If-Modified-Since with cache modified datetime and If-None-Match with ETag
     *
     * @param string $modified
     * @param string $etag
     * @return bool
     */
    protected static function match($modified, $etag)
    {
        $none_match = Request::header('If-None-Match');

        if ($none_match !== null) {
            return $none_match === "\"{$etag}\"";
        }

        $since = Request::header('If-Modified-Since');

        if ($since !== null) {
            return $modified <= strtotime($since);
        }

        return false;
    }

    /**
     * Check if is HEAD or GET - This method can be overridden
     *
     * @param string $method
     * @return bool
     */
    protected static function valid($method)
    {
        return $method === 'GET' || $method === 'HEAD';
    }

    /**
     * Create hash used by cache name and Etag header - This method can be overridden
     *
     * @param string $path
     * @return string
     */
    protected static function createHash($path)
    {
        return \hash('sha256', $path);
    }

    private function error()
    {
        $this->noErrors = false;
        $this->finish(false);
    }

    private function finish($move)
    {
        if ($this->handle !== null) {
            $handle = $this->handle;

            $this->handle = null;

            flock($handle, LOCK_UN);
            fclose($handle);

            if ($move) {
                rename($this->cacheTemp, $this->cache);
            }
        }
    }

    private function write($data, $phase)
    {
        if ($this->noErrors && $this->handle !== null) {
            $callback = $this->writerCallback;

            if ($callback !== null) {
                $data = $callback($data);
            }

            if (fwrite($this->handle, $data) !== strlen($data)) {
                $this->noErrors = false;
                $this->finish(false);
            }
        }

        return $data;
    }

    private function debugWithHeader($flag)
    {
        if ($this->debug) {
            switch ($flag) {
                case self::CACHED:
                    $mode = 'cached';
                    break;

                case self::FAILED:
                    $mode = 'failed';
                    break;

                default:
                    $mode = 'writing';
            }

            header('X-Inphinit-Experimental-Cache: ' . $mode);
        }

        return $flag;
    }
}
