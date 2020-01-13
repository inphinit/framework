<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Helper;
use Inphinit\Storage;

class Config implements \IteratorAggregate
{
    private static $exceptionlevel = 3;
    private $data = array();
    private $path;

    /**
     * Return items from a config file in a object (iterator or with ->)
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($path)
    {
        $this->path = 'application/Config/' . strtr($path, '.', '/') . '.php';

        $this->reload();
    }

    /**
     * Create a Negotiation instance
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Config
     */
    public static function load($path)
    {
        self::$exceptionlevel = 4;

        return new static($path);
    }

    /**
     * Reload configuration from file
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Config
     */
    public function reload()
    {
        $level = self::$exceptionlevel;

        self::$exceptionlevel = 2;

        if (false === \Inphinit\File::exists(INPHINIT_PATH . $this->path)) {
            throw new Exception('File not found ' . $this->path, $level);
        }

        foreach (\UtilsSandboxLoader($this->path) as $key => $value) {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Reload configuration from file
     *
     * @return bool
     */
    public function save()
    {
        if (Storage::createFolder('tmp/cfg')) {
            $wd = preg_replace('#,(\s+|)\)#', '$1)', var_export($this->data, true));
            $path = Storage::temp('<?php' . EOL . 'return ' . $wd . ';' . EOL, 'tmp/cfg');

            if ($path) {
                $response = copy($path, INPHINIT_PATH . $this->path);

                unlink($path);

                return $response;
            }
        }

        return false;
    }

    /**
     * Get all values like array or get specific item by level (multidimensional) using path
     *
     * @param string $path (optional) Path with "dots"
     * @param string $alternative (optional) alternative value does not find the selected value, default is null
     * @return mixed
     */
    public function get($path = null, $alternative = null)
    {
        if ($path === null) {
            return $this->data;
        }

        return Helper::extract($path, $this->data, $alternative);
    }

    /**
     * Set value by path in specific level (multidimensional)
     *
     * @param string $path Path with "dots"
     * @param mixed $value Define value
     * @return \Inphinit\Experimental\Config
     */
    public function set($path, $value)
    {
        $paths = explode('.', $path);

        $key = array_shift($paths);

        $tree = $value;

        foreach (array_reverse($paths) as $item) {
            $tree = array($item => $tree);
        }

        $this->data[$key] = $tree;

        $tree = null;

        return $this;
    }

    /**
     * Magic method for get specific item by ->
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
     * Magic method for set value (this method don't save data)
     *
     * @param string $name
     * @param mixed  $value
     * @return void
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Magic method for check if value exists in top-level
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
        unset($this->data[$name]);
    }

    /**
     * Allow iteration with `for`, `foreach` and `while`
     *
     * Example:
     * <pre>
     * <code>
     * $foo = new Config('file'); //or Config::load('file')
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
        return new \ArrayIterator($this->data);
    }

    public function __destruct()
    {
        $this->data = null;
    }
}
