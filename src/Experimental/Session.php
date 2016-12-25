<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\AppData;

class Session implements \IteratorAggregate
{
    private $handler;
    private $iterator;
    private $data = array();
    private $insertions = array();
    private $deletions = array();
    private $currentId;
    private $currentName;

    public function __construct($name = 'inphinit', $id = null, $savepath = null, array $opts = array())
    {
        if (headers_sent()) {
            Exception::raise('Cannot modify header information - headers already sent', 2);
        }

        AppData::createCommomFolders();

        $savepath = $savepath ? $savepath : (AppData::storagePath() . 'session');
        $savepath = rtrim($savepath, '/') . '/';

        $tmpname = null;

        if ($id === null) {
            if (empty($_COOKIE[$name])) {
                $tmpname = tempnam($savepath, 'S_');
                $this->currentId = substr(basename($tmpname), 2);
                $this->currentId = substr(basename($this->currentId), 0, -4);
            } else {
                $this->currentId = $_COOKIE[$name];
            }
        } else {
            $this->currentId = $id;
        }

        if ($tmpname === null) {
            $tmpname = $savepath . 'S_' . $this->currentId . '.tmp';
        }

        $this->currentName = $name;

        $this->handler = fopen($tmpname, 'a+');

        if ($this->handler === false) {
            Exception::raise('Failed to write session data', 2);
        }

        $opts = $opts + array(
            'expire' => 0, 'path' => '', 'domain' => '',
            'secure' => false, 'httponly' => false
        );

        if (!setcookie($name, $this->currentId, $opts['expire'], $opts['path'], $opts['domain'], $opts['secure'], $opts['httponly']))
        {
            Exception::raise('Failed to set HTTP cookie', 2);
        }

        $opts = null;

        $this->read();
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

    private function write()
    {
        ftruncate($this->handler, 0);
        rewind($this->handler);

        fwrite($this->handler, serialize($this->data));
    }

    public function __clone()
    {
        Exception::raise('Can\'t clone object', 2);
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
    }

    public function __set($name, $value)
    {
        unset($this->deletions[$name]);

        $this->insertions[$name] = $value;
        $this->data = $this->insertions + $this->data;
    }

    public function __isset($name)
    {
        return array_key_exists($name, $this->data);
    }

    public function __unset($name)
    {
        unset($this->data[$name], $this->insertions[$name]);

        $this->deletions[$name] = true;
    }

    public function getIterator()
    {
        return $this->iterator = new \ArrayIterator($this->data);
    }
}
