<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Session
{
    const BYTES_LENGTH = 16;
    const CREATE_TIMEOUT = 10;
    const LOCK_TIMEOUT = 10;

    private $id;
    private $data = array();
    private $handle;
    private $locked = false;

    private $storage;
    private $domain;
    private $expires;
    private $httpOnly = false;
    private $name;
    private $partitioned = false;
    private $path = '/';
    private $sameSite;
    private $secure = false;
    private $storePrefix = '~sess';

    /**
     * Reads and stores session data and creates a cookie
     *
     * @var string $config Configuration file that defines the headers and storage
     * @throws \Inphinit\Exception
     * @throws \ErrorException
     */
    public function __construct($config)
    {
        $this->loadConfigs($config);

        $name = $this->name;

        if (isset($_COOKIE[$name]) && is_string($_COOKIE[$name]) && preg_match('#^[a-f\d]{32}$#', $_COOKIE[$name])) {
            $id = $_COOKIE[$name];
            $filename = $this->storage . '/' . $this->storePrefix . '[' . $id . ']';

            $this->handle = fopen($filename, 'r+');

            if ($this->handle === false) {
                $this->setCookie(true);
                throw new Exception('Invalid session file');
            }

            $this->read();
            $this->id = $id;
        } else {
            $this->id = $this->create($this->handle, $filename);
            $this->setCookie(false);
        }
    }

    /**
     * Save session data
     *
     * @throws \Inphinit\Exception
     */
    public function commit()
    {
        $data = serialize($this->data);

        $this->lock(true);

        ftruncate($this->handle, 0);
        rewind($this->handle);

        $stored = fwrite($this->handle, $data);

        $this->lock(false);

        if ($stored === false || $stored < strlen($data)) {
            throw new Exception('Failed to store session data');
        }
    }

    /**
     * Get current ID from session
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Regenerate data
     *
     * @throws \Inphinit\Exception
     * @throws \ErrorException
     */
    public function regenerate()
    {
        $id = $this->create($dest, $path);
        $source = $this->handle;

        $this->lock(true);

        rewind($source);

        if (stream_copy_to_stream($source, $dest) === false) {
            $this->lock(false);

            fclose($dest);
            unlink($path);

            throw new Exception('Failed to copy session data');
        }

        $this->close();

        $this->handle = $dest;
        $this->id = $id;

        $this->setCookie(false);
    }

    /**
     * Magic method for get session variables (this method also returns variables that have not yet
     * been committed)
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Magic method for set session variables (this method don't commit data)
     *
     * @param string $name
     * @param mixed  $value
     * @throws \Inphinit\Exception
     */
    public function __set($name, $value)
    {
        try {
            serialize($value);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode(), 2, $ex);
        }

        $this->data[$name] = $value;
    }

    /**
     * Magic method for check if variable is setted (this method
     * also returns variables that have not yet been committed)
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic method for unset variable with `unset()` function
     *
     * @param string $name
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function __destruct()
    {
        $this->close();
    }

    private function create(&$handle, &$filename)
    {
        $start = microtime(true);
        $timeout = self::CREATE_TIMEOUT;
        $storage = $this->storage;
        $file = null;
        $id = null;
        $prefix = $this->storePrefix;
        $stream = false;

        while ($stream === false) {
            if (microtime(true) - $start > $timeout) {
                throw new Exception('Create session file timeout', 0, 3);
            }

            $id = self::createId();
            $file = $storage . '/' . $prefix . '[' . $id . ']';
            $stream = fopen($file, 'x+');

            if ($stream === false) {
                usleep(1000);
            }
        }

        $handle = $stream;
        $filename = $file;

        return $id;
    }

    private function read()
    {
        $this->lock(true);

        rewind($this->handle);

        $data = stream_get_contents($this->handle);

        if ($data === false) {
            $this->setCookie(true);
            throw new Exception('Cannot read session data', 0, 3);
        }

        if ($data !== '') {
            try {
                if (PHP_VERSION_ID < 70000) {
                    $data = unserialize($data);
                } else {
                    $data = unserialize($data, array('allowed_classes' => false));
                }
            } catch (\Exception $ex) {
                $this->close();
                $this->setCookie(true);
                throw new Exception($ex->getMessage(), $ex->getCode(), 3, $ex);
            }
        }

        $this->lock(false);

        if (is_array($data) === false) {
            $this->setCookie(true);
            throw new Exception('Cannot unserialize session data', 0, 3);
        }

        $this->data = $data;
    }

    private function close()
    {
        if ($this->handle) {
            $this->lock(false);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    private function setCookie($forceExpires)
    {
        if (headers_sent($file, $line)) {
            $this->close();
            throw new \ErrorException('Cannot set session cookie, headers already sent', 0, E_ERROR, $file, $line);
        }

        if ($forceExpires) {
            $id = '_';
            $expires = '; Expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0';
        } else {
            $id = $this->id;
            $expires = $this->expires;
        }

        $cookie = 'Set-Cookie: ' . $this->name . '=' . $id;
        $secure = ($this->secure === true);

        if ($this->domain !== null) {
            $cookie .= '; Domain=' . $this->domain;
        }

        $cookie .= '; Path=' . $this->path;

        if ($expires !== null) {
            $cookie .= '; Expires=' . $expires;
        }

        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if ($this->partitioned) {
            $cookie .= '; Partitioned';
            $secure = true;
        }

        if ($this->sameSite !== null) {
            $cookie .= '; SameSite=' . $this->sameSite;

            if ($this->sameSite === 'None') {
                $secure = true;
            }
        }

        if ($secure) {
            $cookie .= '; Secure';
        }

        header($cookie, false);
    }

    private function lock($enable)
    {
        if ($this->locked === $enable) {
            return null;
        }

        if ($enable) {
            $start = microtime(true);
            $timeout = self::LOCK_TIMEOUT;
            $handle = $this->handle;

            while (flock($handle, LOCK_EX | LOCK_NB) === false) {
                if (microtime(true) - $start > $timeout) {
                    throw new Exception('Lock session data timeout', 0, 3);
                }

                usleep(1000);
            }

            $this->locked = true;
        } else {
            flock($this->handle, LOCK_UN);
            $this->locked = false;
        }
    }

    private function loadConfigs($config)
    {
        try {
            $opts = new Config($config);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), 0, 3, $ex);
        }

        if (is_string($opts->name) === false || ctype_alpha($opts->name) === false) {
            throw new Exception('Invalid session name configuration', 0, 3);
        }

        $this->name = $opts->name;

        $path = $opts->path;

        if (
            is_string($path) === false ||
            $path === '' ||
            $path[0] !== '/' ||
            preg_match('/[\x00-\x1F\x7F]/', $path) ||
            strpos($path, ';') !== false
        ) {
            throw new Exception('Missing or invalid session cookie path configuration', 0, 3);
        }

        $this->path = $path;

        if ($opts->domain !== null) {
            if (strpbrk($opts->domain, " =,;\t\r\n\013\014") !== false) {
                throw new Exception('Invalid session cookie domain configuration', 0, 3);
            }

            $this->domain = $opts->domain;
        }

        if ($opts->expires !== null) {
            if (is_string($opts->expires) === false) {
                throw new Exception('Invalid session cookie expiration configuration', 0, 3);
            }

            try {
                $date = new \DateTime($opts->expires, new \DateTimeZone('UTC'));
                $this->expires = $date->format('D, d M Y H:i:s \G\M\T');
            } catch (\Exception $ex) {
                throw new Exception($ex->getMessage(), 0, 3, $ex);
            }
        }

        if ($opts->http_only !== null) {
            if (is_bool($opts->http_only) === false) {
                throw new Exception('Invalid session cookie http_only configuration', 0, 3);
            }

            $this->httpOnly = $opts->http_only;
        }

        if ($opts->partitioned !== null) {
            if (is_bool($opts->partitioned) === false) {
                throw new Exception('Invalid session cookie partitioned configuration', 0, 3);
            }

            $this->partitioned = $opts->partitioned;
        }

        $same_site = $opts->same_site;

        if ($same_site !== null) {
            if (
                is_string($same_site) === false ||
                in_array(strtolower($same_site), array('lax', 'none', 'strict')) === false
            ) {
                throw new Exception('Invalid session cookie same_site configuration', 0, 3);
            }

            $this->sameSite = ucfirst(strtolower($same_site));
        }

        if ($opts->secure !== null) {
            if (is_bool($opts->secure) === false) {
                throw new Exception('Invalid session cookie secure configuration', 0, 3);
            }

            $this->secure = $opts->secure;
        }

        if ($opts->store_prefix !== null) {
            if (preg_match('#^[\w~\-]+$#', $opts->store_prefix) !== 1) {
                throw new Exception('Invalid session store_prefix configuration', 0, 3);
            }

            $this->storePrefix = $opts->store_prefix;
        }

        if ($opts->storage !== null) {
            if (is_dir($opts->storage) === false) {
                throw new Exception('Invalid session storage path', 0, 3);
            }

            $this->storage = $opts->storage;
        } else {
            $this->storage = INPHINIT_SYSTEM . '/storage/session';
        }
    }

    private static function createId()
    {
        if (PHP_VERSION_ID >= 70000) {
            try {
                $bin = \random_bytes(self::BYTES_LENGTH);
            } catch (\Exception $ex) {
                throw new Exception($ex->getMessage(), 0, 3, $ex);
            }
        } elseif (function_exists('mcrypt_create_iv')) {
            $bin = \mcrypt_create_iv(self::BYTES_LENGTH, \MCRYPT_DEV_URANDOM);

            if ($bin === false || strlen($bin) !== self::BYTES_LENGTH) {
                throw new Exception('MCRYPT: Unable to generate random bytes', 0, 3);
            }
        } else {
            throw new Exception('No supported CSPRNG source is available', 0, 3);
        }

        return \bin2hex($bin);
    }
}
