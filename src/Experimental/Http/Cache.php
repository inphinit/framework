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
    private $headerStorage;
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
        if ($path === null) {
            $path = INPHINIT_PATH;

            if (isset($_SERVER['QUERY_STRING'])) {
                $path .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        $hash = static::createHash($path);

        if (is_string($hash) === false || trim($hash) === '' || strpos($hash, '"') !== false) {
            $reflect = new \ReflectionClass($this);
            $chash = $reflect->getMethod('createHash');
            $file = $chash->getFileName();
            $line = $chash->getStartLine();
            $message = 'createHash() created an invalid hash';

            if ($file === false || $line === false) {
                throw new Exception($message);
            }

            throw new \ErrorException($message, 0, E_ERROR, $file, $line);
        }

        if ($method === null) {
            $method = $_SERVER['REQUEST_METHOD'];
        }

        if ($storage === null) {
            $storage = 'storage/cache/output';
        } else {
            $storage = ltrim($storage, '/');
        }

        $this->hash = $hash;
        $this->method = strtoupper($method);
        $this->storage = $storage;

        if (App::config('environment') === 'development') {
            $this->debug = true;
        }
    }

    /*
     * Set a method to overwrite the buffer
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
     * @param int $chuckSize
     * @throws \ErrorException
     * @return int
     */
    public function start($expires = 3600, $chuckSize = 1024)
    {
        self::checkHeadersSent();

        if (static::valid($this->method) === false) {
            return $this->debugWithHeader(self::FAILED);
        }

        if ($this->started) {
            throw new Exception('The cache has already been started');
        }

        $this->started = true;

        $hash = $this->hash;

        $time = time();

        $cache = INPHINIT_SYSTEM . '/' . $this->storage . '/' . $hash;

        $this->headerStorage = $cache . '.headers';

        if (is_file($cache) && ($modified = filemtime($cache)) > ($time - $expires) && $this->sendHeaders()) {
            $this->debugWithHeader(self::CACHED);

            Response::cache($expires, $modified);
            header('Etag: "' . $hash . '"');

            if (static::match($hash, $modified)) {
                Response::status(304);
            } elseif ($this->method !== 'HEAD') {
                File::output($cache);
            }

            return self::CACHED;
        }

        if (ob_get_level() > 0) {
            ob_end_flush();
        }

        self::checkHeadersSent();

        $cache_temp = $cache . '.tmp';

        $handle = fopen($cache_temp, 'cb');

        if ($handle !== false) {
            $this->handle = $handle;

            // Caution: If the flock fails, it is likely because another request is writing to the cache
            if (
                flock($handle, LOCK_EX | LOCK_NB) &&
                ftruncate($this->handle, 0) &&
                ob_start(array($this, 'write'), $chuckSize, PHP_OUTPUT_HANDLER_FLUSHABLE)
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
     * Stops the cache and writes the content and headers obtained so far
     * Note: Under normal circumstances, `__destruct` will execute this method automatically
     */
    public function stop()
    {
        if ($this->noErrors && $this->handle) {
            $contents = ob_get_contents();

            if ($contents !== false) {
                $this->write($contents, 0);
            }

            // If the write() method did not fail
            if ($this->noErrors) {
                $headers = implode("\n", headers_list());

                $dheaders = $this->headerStorage;
                $theaders = $dheaders . '.tmp';

                if (
                    file_put_contents($theaders, $headers, LOCK_EX) === false ||
                    rename($theaders, $dheaders) === false
                ) {
                    $this->noErrors = false;
                }
            }
        }

        $this->finish($this->noErrors);
    }

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Check If-None-Match with ETag and If-Modified-Since with cache modified datetim
     *
     * @param string $etag
     * @param int    $modified
     * @return bool
     */
    protected static function match($etag, $modified)
    {
        $none_match = Request::header('If-None-Match');

        if ($none_match !== null) {
            return $none_match === "\"{$etag}\"";
        }

        $since = Request::header('If-Modified-Since');

        if ($since !== null) {
            $timestamp = strtotime($since);
            return $timestamp !== false && $modified <= $timestamp;
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

    private function checkHeadersSent()
    {
        if (headers_sent($file, $line)) {
            throw new \ErrorException('Headers already sent', 0, E_ERROR, $file, $line);
        }
    }

    private function sendHeaders()
    {
        $headers = file($this->headerStorage, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);

        if ($headers === false) {
            return false;
        }

        foreach ($headers as $header) {
            header($header);
        }

        return true;
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
