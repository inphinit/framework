<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Storage;

class Session implements \IteratorAggregate
{
    private $handle;
    private $iterator;
    private $savepath;
    private $prefix = '~sess_';
    private $data = array();
    private $insertions = array();
    private $deletions = array();
    private $currentId;
    private $currentName;
    private $opts;

    /**
     * Create cookie session and configure session
     *
     * @param string      $name
     * @param string|null $id
     * @param array       $opts
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($name = 'inphinit', $id = null, array $opts = array())
    {
        self::raise();

        $this->savepath = Storage::resolve('session');

        if ($id === null) {
            if (empty($_COOKIE[$name])) {
                $tmpname = Storage::temp(null, 'session', $this->prefix, '');

                if ($tmpname === false) {
                    throw new Exception('Failed to create session file', 2);
                }

                $cid = substr(basename($tmpname), 5);
            } else {
                $cid = $_COOKIE[$name];
            }

            $this->currentId = $cid;
        } else {
            $this->currentId = $id;
        }

        if (empty($tmpname)) {
            $tmpname = $this->savepath . '/' . $this->prefix . $this->currentId;
        }

        $this->handle = fopen($tmpname, 'a+');

        if ($this->handle === false) {
            throw new Exception('Failed to write session data', 2);
        }

        $opts = $opts + array(
            'path' => '/',
            'expire' => 0,
            'domain' => '',
            'secure' => false,
            'httponly' => false
        );

        $this->opts = (object) $opts;

        $opts = null;

        $this->currentName = $name;

        $this->cookie();
        $this->read();
    }

    /**
     * Lock session file and save variables
     * @param bool $unlock
     * @return void
     */
    public function commit($unlock = true)
    {
        if (empty($this->insertions) && empty($this->deletions)) {
            return null;
        }

        $data = $this->getData();

        if ($data !== '') {
            $this->data = $this->insertions + $data;

            foreach ($this->deletions as $key => $value) {
                unset($this->data[$key]);
            }
        } else {
            $this->data = $this->insertions;
        }

        $this->insertions = array();
        $this->deletions = array();

        $this->write();

        if ($unlock) {
            flock($this->handle, LOCK_UN);
        }

        $this->iterator = new \ArrayIterator($this->data);
    }

    /**
     * Regenerate ID
     *
     * @param string|null $id
     * @param bool        $trydeleteold
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function regenerate($id = null, $trydeleteold = false)
    {
        self::raise();

        $old = $this->savepath . '/' . $this->prefix . $this->currentId;

        $this->commit(false);

        if (empty($id)) {
            $tmpname = Storage::temp(null, 'session', $this->prefix, '');

            if ($tmpname === false) {
                throw new Exception('Failed to create new session file', 2);
            }

            $id = substr(basename($tmpname), 5);
        } else {
            $tmpname = $this->savepath . '/' . $this->prefix . $id;
        }

        if (copy($this->handle, $tmpname) === false) {
            throw new Exception('Failed to copy new old session', 2);
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);

        if ($trydeleteold) {
            unlink($old);
        }

        $this->handle = fopen($tmpname, 'a+');

        $this->currentId = $id;

        $this->cookie();
    }

    /**
     * Set cookie
     *
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    private function cookie()
    {
        if (!setcookie(
            $this->currentName,
            $this->currentId,
            $this->opts->expire,
            $this->opts->path,
            $this->opts->domain,
            $this->opts->secure,
            $this->opts->httponly
        )
        ) {
            throw new Exception('Failed to set HTTP cookie', 3);
        }
    }

    /**
     * Read session file
     *
     * @return void
     */
    private function read()
    {
        $data = $this->getData();

        if ($data) {
            $this->data = $data;
            $data = null;
        }

        flock($this->handle, LOCK_UN);

        $this->iterator = new \ArrayIterator($this->data);
    }

    private function getData()
    {
        $this->lock();

        rewind($this->handle);

        $data = trim(stream_get_contents($this->handle));

        if ($data) {
            return unserialize($data);
        }

        return '';
    }

    /**
     * Quick lock session
     *
     * @return void
     */
    private function lock()
    {
        if (flock($this->handle, LOCK_EX) === false) {
            usleep(5000);
            $this->lock();
        }
    }

    /**
     * Write variables in session file
     *
     * @return void
     */
    private function write()
    {
        ftruncate($this->handle, 0);
        rewind($this->handle);

        fwrite($this->handle, serialize($this->data));
    }

    private static function raise()
    {
        if (headers_sent($filename, $line)) {
            throw new Exception("HTTP headers already sent by $filename:$line", 3);
        }
    }

    /**
     * Prevent clone session object
     *
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __clone()
    {
        throw new Exception('Can\'t clone object', 2);
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
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
    }

    /**
     * Magic method for set session variables (this method don't commit data)
     *
     * @param string $name
     * @param mixed  $value
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __set($name, $value)
    {
        try {
            serialize($value);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), 2);
        }

        unset($this->deletions[$name]);

        $this->insertions[$name] = $value;
        $this->data = $this->insertions + $this->data;

        $this->iterator = new \ArrayIterator($this->data);
    }

    /**
     * Magic method for check if variable is setted (this method also returns variables that have not yet
     * been committed)
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Magic method for unset variable with `unset()` function
     *
     * @param string $name
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data[$name], $this->insertions[$name]);

        $this->deletions[$name] = true;

        $this->iterator = new \ArrayIterator($this->data);
    }

    /**
     * Allow iteration with `for`, `foreach` and `while`
     *
     * Example:
     * <pre>
     * <code>
     * $foo = new Session;
     *
     * foreach ($foo as $key => $value) {
     *     var_dump($key, $value);
     *     echo EOL;
     * }
     * </code>
     * </pre>
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return $this->iterator;
    }

    public function __destruct()
    {
        $this->commit();

        if ($this->handle) {
            fclose($this->handle);
        }

        $this->opts =
        $this->data =
        $this->iterator =
        $this->deletions =
        $this->insertions =
        $this->handle = null;
    }
}
