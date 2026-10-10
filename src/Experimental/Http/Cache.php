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

    const DEFAULT_CHUNK_SIZE = 1048576;

    private $cache;
    private $cacheTemp;
    private $debug = false;
    private $expiresAt;
    private $finishContentLength = 0;
    private $handle;
    private $hash;
    private $headerStorage;
    private $method;
    private $noErrors = true;
    private $started = false;
    private $storage = 'storage/cache/output';
    private $lifetime;
    private $writing = false;

    /*
     * Define HTTP and request target. By default, the query string is not used;
     * combine INPHINIT_PATH with $_SERVER[QUERY_STRING].
     *
     * @param string|null $method Optional. Default is $_SERVER[REQUEST_METHOD].
     * @param string|null $target Optional. Default is INPHINIT_PATH.
     * @param string|null $query  Optional. Default is not to use a querystring.
     */
    public function __construct($method = null, $target = null, $querystring = null)
    {
        if ($method === null) {
            if (isset($_SERVER['REQUEST_METHOD'][0])) {
                $method = $_SERVER['REQUEST_METHOD'];
            } else {
                throw new Exception('REQUEST_METHOD not defined');
            }
        }

        if ($target === null) {
            $target = INPHINIT_PATH;
        }

        if ($querystring !== null) {
            $target .= '?' . $querystring;
        }

        $hash = static::createHash($target);

        if (
            is_string($hash) === false ||
            trim($hash) === '' ||
            ctype_print($hash) === false ||
            strpbrk($hash, ' "\\/') !== false
        ) {
            throw new Exception('createHash() created an invalid hash');
        }

        if (App::config('environment') === 'development') {
            $this->debug = true;
        }

        $this->hash = $hash;
        $this->method = strtoupper($method);
    }

    /*
     * Set how long the cache should remain valid before it is refreshed
     * Note: This value is also used to set the Expires header.
     *
     * @param int $minutes
     * @param int $hours
     * @param int $days
     */
    public function setLifetime($days, $hours, $minutes)
    {
        if ($days < 0 || $days > 365) {
            throw new Exception('Days must be between 0 and 365');
        }

        if ($hours < 0 || $hours > 23) {
            throw new Exception('Hours must be between 0 and 23');
        }

        if ($minutes < 0 || $minutes > 59) {
            throw new Exception('Minutes must be between 0 and 59');
        }

        $lifetime = ($minutes * 60) + ($hours * 3600) + ($days * 86400);

        if ($lifetime < 1) {
            throw new Exception('Lifetime must be greater than zero');
        }

        $this->lifetime = $lifetime;
    }

    /*
     * Set storage location
     *
     * @param string $path
     */
    public function setStorage($path)
    {
        $full = Storage::path($path);

        if (is_dir($full) === false || is_writable($full) === false) {
            throw new Exception('Invalid directory');
        }

        $this->storage = trim($path, '/');
    }

    /**
     * Starts recording the buffer to the cache, or serves it directly if a fresh copy exists.
     *
     * - Returns `CACHED` and serves the stored response if a fresh cache exists
     * - Returns `WRITING` if recording of the current response just started
     * - Returns `FAILED` if the method/cache is not writable, or the cache could not be created
     *
     * @param int $chunkSize
     * @throws \ErrorException
     * @return int
     */
    public function start($chunkSize = null)
    {
        if ($this->started) {
            throw new Exception('Cache has already been started');
        }

        if ($chunkSize === null) {
            $chunkSize = self::DEFAULT_CHUNK_SIZE;
        }

        $lifetime = $this->lifetime;

        if ($lifetime === null) {
            throw new Exception('Lifetime has not been defined');
        }

        if (headers_sent($file, $line)) {
            throw new \ErrorException('Cache cannot start, headers already sent', 0, E_ERROR, $file, $line);
        }

        if (static::valid(http_response_code(), $this->method) === false) {
            return $this->debugWithHeader(self::FAILED);
        }

        $this->started = true;

        $hash = $this->hash;

        $cache = INPHINIT_SYSTEM . '/' . $this->storage . '/' . $hash;

        $this->headerStorage = $cache . '.headers';

        $fmtime = @filemtime($cache);

        if ($fmtime !== false && ($fmtime + $lifetime) > time() && $this->sendCachedHeaders()) {
            $this->debugWithHeader(self::CACHED);

            $this->setHeaders(filesize($cache), $fmtime);

            $etag = "{$hash}-{$fmtime}";

            if (static::match($etag, $fmtime)) {
                Response::status(304);
            } elseif ($this->method !== 'HEAD') {
                File::output($cache);
            }

            return self::CACHED;
        }

        $cache_temp = $cache . '.tmp';

        $handle = fopen($cache_temp, 'cb');

        if ($handle !== false) {
            $this->handle = $handle;

            $flags = PHP_OUTPUT_HANDLER_FLUSHABLE | PHP_OUTPUT_HANDLER_REMOVABLE;

            // Caution: If the flock fails, it is likely because another request is writing to the cache
            if (
                flock($handle, LOCK_EX | LOCK_NB) &&
                ftruncate($handle, 0) &&
                ob_start(array($this, 'write'), $chunkSize, $flags)
            ) {
                $this->writing = true;
                $this->cache = $cache;
                $this->cacheTemp = $cache_temp;

                $error = array($this, 'error');

                Event::on('error', function ($type, $message, $file, $line) use ($error) {
                    $error();
                });

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
        $headers = $this->headersList();

        if ($this->noErrors && $this->handle !== null) {
            if (static::valid(http_response_code(), $this->method)) {
                $contents = ob_get_contents();

                if ($contents !== false) {
                    $this->write($contents, 0);
                }
            } else {
                $this->noErrors = false;
            }

            // If the write() method did not fail
            if ($this->noErrors) {
                $dheaders = $this->headerStorage;
                $theaders = $dheaders . '.tmp';
                $wheaders = implode("\n", $headers);

                if (
                    file_put_contents($theaders, $wheaders, LOCK_EX) === false ||
                    rename($theaders, $dheaders) === false
                ) {
                    $this->noErrors = false;
                }
            }
        }

        $this->finish($this->noErrors);

        if ($this->writing) {
            $this->writing = false;

            ob_end_flush();
        }
    }

    public function __destruct()
    {
        $this->stop();
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
     * Checks if the HTTP status code and method are valid for caching – This method can be overridden.
     *
     * @param int $status
     * @param string $method
     * @return bool
     */
    protected static function valid($status, $method)
    {
        return $status === 200 && ($method === 'GET' || $method === 'HEAD');
    }

    private function headersList()
    {
        $skip = array(
            'content-length', 'etag', 'last-modified', 'expires', 'date', 'age',
            'transfer-encoding', 'connection', 'keep-alive', 'x-powered-by',
            'x-inphinit-experimental-cache', 'cache-control', 'pragma',
            'x-request-id', 'x-correlation-id', 'server-timing', 'traceparent',
        );

        $abort = array(
            'set-cookie', 'www-authenticate', 'location', 'refresh',
        );

        $vary = '/\*|\b(?:cookie|authorization|accept-language|user-agent)\b/i';

        $headers = array();

        foreach (headers_list() as $header) {
            $pos = explode(':', $header, 2);

            if (isset($pos[1]) === false) {
                continue;
            }

            $name = strtolower(trim($pos[0]));

            if (in_array($name, $abort, true)) {
                $this->noErrors = false;
                break;
            }

            if ($name === 'vary' && preg_match($vary, trim($pos[1])) === 1) {
                $this->noErrors = false;
                break;
            }

            if (in_array($name, $skip, true) === false) {
                $headers[] = $header;
            }
        }

        return $headers;
    }

    private function sendCachedHeaders()
    {
        $headers = file($this->headerStorage, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

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

            $cache_temp = $this->cacheTemp;

            if ($move && rename($cache_temp, $this->cache) && headers_sent() === false) {
                // If this request can't include the header, the next one will
                $this->setHeaders($this->finishContentLength, filemtime($this->cache));
            }
        }
    }

    private function write($data, $phase)
    {
        if ($this->noErrors && $this->handle !== null) {
            $size = strlen($data);

            if (fwrite($this->handle, $data) !== $size) {
                $this->noErrors = false;
                $this->finish(false);
            } else {
                $this->finishContentLength += $size;
            }
        }

        return $this->method === 'HEAD' ? '' : $data;
    }

    private function setHeaders($contenLength, $lastModified)
    {
        $expires = $this->lifetime + $lastModified;
        $hash = $this->hash;

        header("Etag: \"{$hash}-{$lastModified}\"");
        header('Expires: ' . gmdate('D, d M Y H:i:s', $expires) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

        // Caution: Firefox requires Content-Length for HTTP conditional (If-None-Match/If-Modified-Since).
        // See: https://bugzilla.mozilla.org/show_bug.cgi?id=2077994
        header('Content-Length: ' . $contenLength);
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
