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
    const ATTEMPTS_TEMP = 100;
    const LOCK_TIMEOUT = 10;

    private $id;
    private $name;
    private $data = array();
    private $handle;
    private $options;
    private $autocommit = false;

    private static $tempDir;

    protected static $filename = '~sess[%s]';

    /**
     * Create cookie session and configure session
     *
     * @param string  $name
     * @param boolean $autocommit
     * @param array   $options
     * @throws \Inphinit\Exception
     */
    public function __construct($name, $autocommit = false, array $options = array())
    {
        if (headers_sent()) {
            throw new Exception('headers sent');
        }

        if (strpbrk(self::$filename, '/\\') !== false) {
            throw new Exception('Invalid filename: ' . self::$filename);
        }

        self::directory();

        $this->name = $name;

        $this->options = $options + array(
            'path' => '/',
            'expire' => 0,
            'domain' => '',
            'secure' => false,
            'httponly' => false
        );

        $this->autocommit = $autocommit === true;

        $request = false;

        if (isset($_COOKIE[$name]) && preg_match('#^\d{1,2}_[a-zA-Z0-9]{8,32}$#', $_COOKIE[$name])) {
            $id = $_COOKIE[$name];

            $filename = sprintf(self::$filename, $id);

            $this->handle = fopen(self::$tempDir . '/' . $filename, 'c+');

            if ($this->handle === false) {
                throw new Exception('Invalid session file');
            }

            $this->read();
        } else {
            $id = self::createTemporary($this->handle);
            $this->setCookie($id);
        }

        $this->id = $id;
    }

    /**
     * Set or get temp directory
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return string|void
     */
    public static function directory($path = null)
    {
        if ($path === null) {
            if (self::$tempDir === null) {
                self::$tempDir = INPHINIT_SYSTEM . '/storage/session';
            }

            return self::$tempDir;
        }

        if (is_dir($path) && is_writable($path)) {
            self::$tempDir = $path;
        } else {
            throw new Exception($path . ' is not writable or invalid');
        }
    }

    /**
     * Remove old files
     *
     * @param int $expires
     * @param int $max
     * @return int
     */
    public static function clean($expires = 0, $max = 100)
    {
        $count = 0;
        $path = self::directory();
        $handle = opendir($path);

        if ($handle) {
            if ($expires < 0) {
                $expires = App::config('session_expires');
            }

            $expires = time() - $expires;

            $path .= '/';

            while ($count < $max && ($entry = readdir($handle)) !== false) {
                $entry = $path . $entry;

                if (is_file($entry) && filemtime($entry) < $expires && unlink($entry)) {
                    ++$count;
                }
            }

            closedir($handle);
        }

        return $count;
    }

    /**
     * Save session data
     *
     * @throws \Inphinit\Exception
     */
    public function commit()
    {
        $this->setLock(true);

        ftruncate($this->handle, 0);
        rewind($this->handle);

        $written = fwrite($this->handle, serialize($this->data));

        $this->setLock(false);

        if ($written === false) {
            throw new Exception('Failed to commit');
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
     */
    public function regenerate()
    {
        if (headers_sent()) {
            throw new Exception('headers sent');
        }

        $id = self::createTemporary($dest, $path);

        rewind($this->handle);

        if (stream_copy_to_stream($this->handle, $dest) === false) {
            fclose($dest);
            unlink($path);

            throw new Exception('Failed copy data');
        }

        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }

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
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        $this->data[$name] = $value;

        if ($this->autocommit) {
            $this->commit();
        }
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

        if ($this->autocommit) {
            $this->commit();
        }
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);
        }
    }

    private function read()
    {
        $this->setLock(true);

        rewind($this->handle);

        $data = stream_get_contents($this->handle);

        try {
            if (PHP_VERSION_ID < 70000) {
                $data = unserialize($data);
            } else {
                $data = unserialize($data, array('allowed_classes' => false));
            }
        } catch (\Exception $e) {
            $this->setLock(false);

            throw new Exception($e->getMessage(), $e->getCode(), 3);
        }

        if (is_array($data)) {
            $this->data = $data;
        }

        $this->setLock(false);
    }

    private function setCookie($id)
    {
        if (setcookie(
            $this->name,
            $id,
            $this->options['expire'],
            $this->options['path'],
            $this->options['domain'],
            $this->options['secure'],
            $this->options['httponly']
        ) === false) {
            if ($this->handle) {
                fclose($this->handle);
            }

            throw new Exception('Failed to set HTTP cookie', 0, 3);
        }
    }

    private function setLock($lock)
    {
        if ($lock) {
            $start = microtime(true);
            $timeout = self::LOCK_TIMEOUT;

            while (flock($this->handle, LOCK_EX | LOCK_NB) === false) {
                if (microtime(true) - $start > $timeout) {
                    throw new Exception('Lock timeout', 0, 3);
                }

                usleep(1000);
            }
        } else {
            flock($this->handle, LOCK_UN);
        }
    }

    private static function createTemporary(&$handle, &$path = null)
    {
        $count = 0;
        $cpath = null;
        $dir = self::$tempDir;
        $fname = self::$filename;
        $handle = false;
        $id = '';

        while ($handle === false && $count < self::ATTEMPTS_TEMP) {
            ++$count;

            $id = uniqid("{$count}_");
            $name = sprintf($fname, $id);
            $cpath = $dir . '/' . $name;
            $handle = fopen($cpath, 'x+');
        }

        if ($handle === false) {
            throw new Exception('Failed to create session file', 0, 3);
        }

        $path = $cpath;

        return $id;
    }
}
