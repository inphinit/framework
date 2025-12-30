<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Session
{
    const LOCK_TIMEOUT = 10;
    const STORAGE_ATTEMPTS = 100;
    const RANDOM_BYTES_SIZE = 16;

    private $id;
    private $data = array();
    private $handle;
    private $locked = false;
    private $native = false;

    private $directory;
    private $domain;
    private $expires;
    private $httpOnly = false;
    private $name;
    private $partitioned = false;
    private $path = '/';
    private $sameSite;
    private $secure = false;
    private $storePrefix = '~sess[%s]';

    /**
     * Reads and stores session data and creates a cookie
     *
     * @var string $config Configuration file that defines the headers and storage
     * @throws \Inphinit\Exception
     * @throws \ErrorException
     */
    public function __construct($config)
    {
        $this->native = function_exists('random_bytes');

        if ($this->native === false && function_exists('openssl_random_pseudo_bytes') === false) {
            if (PHP_VERSION_ID < 70000) {
                throw new Exception('Use a version of PHP that supports OpenSSL, or install the extension, depending on your environment');
            } else {
                throw new Exception('Missing support, enable random_bytes - see disable_functions');
            }
        }

        $this->loadConfigs($config);

        $name = $this->name;

        if (isset($_COOKIE[$name][0]) && preg_match('#^[a-f\d]{32}$#', $_COOKIE[$name])) {
            $id = $_COOKIE[$name];
            $store_name = $this->storePrefix;
            $filename = sprintf($store_name, $id);

            $this->handle = fopen($this->directory . '/' . $filename, 'c+');

            if ($this->handle === false) {
                throw new Exception('Invalid session file');
            }

            $this->read();
            $this->id = $id;
        } else {
            $this->id = $this->create($this->handle, $path);
            $this->setCookie();
        }
    }

    /**
     * Save session data
     *
     * @throws \Inphinit\Exception
     */
    public function commit()
    {
        $this->lock(true);

        ftruncate($this->handle, 0);
        rewind($this->handle);

        $stored = fwrite($this->handle, serialize($this->data));

        $this->lock(false);

        if ($stored === false) {
            throw new Exception('Failed to store data', 0, 3);
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

        rewind($source);

        if (stream_copy_to_stream($source, $dest) === false) {
            fclose($dest);
            unlink($path);

            throw new Exception('Failed copy data');
        }

        $this->close();
        $this->setCookie($id);
        $this->handle = $dest;
        $this->id = $id;
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
        } catch (\Exception $ee) {
            throw new Exception($ee->getMessage(), $ee->getCode(), 2, $ee);
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

    private function create(&$handle, &$path)
    {
        $attempts = self::STORAGE_ATTEMPTS;
        $count = 0;
        $dir = $this->directory;
        $name = null;
        $store_name = $this->storePrefix;
        $stream = false;
        $id = null;

        while ($stream === false && $count < $attempts) {
            ++$count;

            $id = $this->createId();
            $name = sprintf($store_name, $id);
            $stream = fopen($dir . '/' . $name, 'x+');
        }

        if ($stream === false) {
            throw new Exception('Failed to create session file', 0, 3);
        }

        $handle = $stream;
        $path = $dir . '/' . $name;

        return $id;
    }

    private function read()
    {
        $this->lock(true);

        rewind($this->handle);

        $data = stream_get_contents($this->handle);

        try {
            if (PHP_VERSION_ID < 70000) {
                $data = unserialize($data);
            } else {
                $data = unserialize($data, array('allowed_classes' => false));
            }
        } catch (\Exception $ee) {
            $this->close();
            throw new Exception($ee->getMessage(), $ee->getCode(), 3, $ee);
        }

        if (is_array($data)) {
            $this->data = $data;
        }

        $this->lock(false);
    }

    private function close()
    {
        if ($this->handle) {
            $this->lock(false);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    private function setCookie()
    {
        if (headers_sent($file, $line)) {
            $this->close();
            throw new \ErrorException('Cannot set cookie, headers already sent', 0, E_ERROR, $file, $line);
        }

        $cookie = 'Set-Cookie: ' . $this->name . '=' . $this->id;
        $secure = $this->secure;

        if ($this->domain) {
            $cookie .= '; Domain=' . $this->domain;
        }

        if ($this->path) {
            $cookie .= '; Path=' . $this->path;
        }

        if ($this->expires) {
            $cookie .= '; Expires=' . $this->expires;
        }

        if ($this->httpOnly) {
            $cookie .= '; HttpOnly';
        }

        if ($this->partitioned) {
            $cookie .= '; Partitioned';
            $secure = true;
        }

        if ($this->sameSite) {
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
                    throw new Exception('Lock timeout', 0, 3);
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
        } catch (\Exception $ee) {
            throw new Exception($ee->getMessage(), 0, 3, $ee);
        }

        if (is_string($opts->name) === false || ctype_alpha($opts->name) === false) {
            throw new Exception('Invalid name', 0, 3);
        }

        $this->name = $opts->name;

        $path = $opts->path;

        if (
            is_string($path) === false ||
            $path[0] !== '/' ||
            preg_match('/[\x00-\x1F\x7F]/', $path) ||
            strpos($path, ';') !== false
        ) {
            throw new Exception('Invalid path', 0, 3);
        }

        if ($opts->domain !== null) {
            if (strpbrk($opts->domain, "=,; \t\r\n\013\014") !== false) {
                throw new Exception('Invalid domain', 0, 3);
            }

            $this->domain = $opts->domain;
        }

        if ($opts->expires !== null) {
            if (is_string($opts->expires) === false) {
                throw new Exception('Invalid expires', 0, 3);
            }

            try {
                $date = new \DateTime($opts->expires, new \DateTimeZone('UTC'));
                $this->expires = $date->format('D, d M Y H:i:s \G\M\T');
            } catch (\Exception $ee) {
                throw new Exception($ee->getMessage(), 0, 3, $ee);
            }
        }

        if ($opts->http_only !== null) {
            if (is_bool($opts->http_only) === false) {
                throw new Exception('Invalid http_only', 0, 3);
            }

            $this->httpOnly = $opts->http_only;
        }

        if ($opts->partitioned !== null) {
            if (is_bool($opts->partitioned) === false) {
                throw new Exception('Invalid partitioned', 0, 3);
            }

            $this->partitioned = $opts->partitioned;
        }

        $same_site = $opts->same_site;

        if ($same_site !== null) {
            if (
                is_string($same_site) === false ||
                in_array(strtolower($same_site), array('lax', 'none', 'strict')) === false
            ) {
                throw new Exception('Invalid same_site', 0, 3);
            }

            $this->sameSite = ucfirst($same_site);
        }

        if ($opts->secure !== null) {
            if (is_bool($opts->secure) === false) {
                throw new Exception('Invalid secure', 0, 3);
            }

            $this->secure = $opts->secure;
        }

        if ($opts->store_prefix !== null) {
            if (preg_match('#^[\w~\-]+$#', $opts->store_prefix) !== 1) {
                throw new Exception('Invalid store_prefix', 0, 3);
            }

            $this->storePrefix = $opts->store_prefix . '[%s]';
        }

        if ($opts->directory !== null) {
            if (is_dir($opts->directory) === false) {
                throw new Exception('Invalid directory', 0, 3);
            }

            $this->directory = $opts->directory;
        } else {
            $this->directory = INPHINIT_SYSTEM . '/storage/session';
        }
    }

    private function createId()
    {
        try {
            if ($this->native) {
                $bin = \random_bytes(self::RANDOM_BYTES_SIZE);
            } else {
                // Returns false on failure in PHP<7.3
                // Throws an exception in case of failure in PHP>=7.4
                $bin = \openssl_random_pseudo_bytes(self::RANDOM_BYTES_SIZE);

                if ($bin === false) {
                    throw new Exception('OpenSSL: Unable to generate a pseudo-random byte sequence', 0, 3);
                }
            }
        } catch (\Exception $ee) {
            throw new Exception($ee->getMessage(), 0, 3, $ee);
        }

        return \bin2hex($bin);
    }
}
