<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Storage;
use Inphinit\Response;

class Session implements \IteratorAggregate
{
    private $handler;
    private $iterator;
    private $data = array();
    private $insertions = array();
    private $deletions = array();
    private $currentId;
    private $currentName;

    /**
     * Create cookie session and configure session
     *
     * @param  string $name
     * @param  string $id
     * @param  array  $opts
     * @throws Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($name = 'inphinit', $id = null, array $opts = array())
    {
        if (headers_sent()) {
            throw new Exception('Cannot modify header information - headers already sent', 2);
        }

        $savepath = Storage::resolve('session');

        $tmpname = null;
        $prefix = 'sess_';

        if ($id === null) {
            if (empty($_COOKIE[$name])) {
                $tmpname = Storage::temp(null, 'session', $prefix, '');

                if ($tmpname === false) {
                    throw new Exception('Failed to create session file', 2);
                }

                $cid = substr(basename($tmpname), 5);

                $this->currentId = $cid;
            } else {
                $this->currentId = $_COOKIE[$name];
            }
        } else {
            $this->currentId = $id;
        }

        if ($tmpname === null) {
            $tmpname = $savepath . '/' . $prefix . $this->currentId;
        }

        $this->currentName = $name;

        $this->handler = fopen($tmpname, 'a+');

        if ($this->handler === false) {
            throw new Exception('Failed to write session data', 2);
        }

        $opts = $opts + array(
            'expire' => 0, 'path' => '/', 'domain' => '',
            'secure' => false, 'httponly' => false
        );

        if (!setcookie($name, $this->currentId, $opts['expire'], $opts['path'], $opts['domain'], $opts['secure'], $opts['httponly']))
        {
            throw new Exception('Failed to set HTTP cookie', 2);
        }

        $opts = null;

        $this->read();
    }

    /**
     * Lock session file and save variables
     *
     * @return void
     */
    public function commit()
    {
        if (empty($this->insertions) && empty($this->deletions)) {
            return null;
        }

        if (flock($this->handler, LOCK_EX) === false) {
            usleep(10000);
            $this->commit();
            return null;
        }

        $data = '';

        rewind($this->handler);

        while (feof($this->handler) === false) {
            $data .= fread($this->handler, 8192);
        }

        $data = trim($data);

        if ($data !== '') {
            $data = unserialize($data);

            $this->data = $this->insertions + $data;

            $data = null;

            foreach ($this->deletions as $key => $value) {
                unset($this->data[$key]);
            }
        } else {
            $this->data = $this->insertions;
        }

        $this->insertions = array();
        $this->deletions = array();

        $this->write();

        flock($this->handler, LOCK_UN);
    }

    /**
     * Read session file
     *
     * @return void
     */
    private function read()
    {
        if (flock($this->handler, LOCK_EX) === false) {
            usleep(10000);
            $this->read();
            return null;
        }

        $data = '';

        rewind($this->handler);

        while (feof($this->handler) === false) {
            $data .= fread($this->handler, 8192);
        }

        $data = trim($data);

        if ($data !== '') {
            $this->data = unserialize($data);
            $data = null;
        }

        flock($this->handler, LOCK_UN);
    }

    /**
     * Write variables in session file
     *
     * @return void
     */
    private function write()
    {
        ftruncate($this->handler, 0);
        rewind($this->handler);

        fwrite($this->handler, serialize($this->data));
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
     * @param string $value
     * @return void
     */
    public function __set($name, $value)
    {
        unset($this->deletions[$name]);

        $this->insertions[$name] = $value;
        $this->data = $this->insertions + $this->data;
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
     *      var_dump($key, $value);
     *      echo EOL;
     * }
     * </code>
     * </pre>
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return $this->iterator = new \ArrayIterator($this->data);
    }

    public function __destruct()
    {
        $this->commit();

        if ($this->handler) {
            fclose($this->handler);
        }

        $this->data =
        $this->iterator =
        $this->deletions =
        $this->insertions =
        $this->handler = null;
    }
}
