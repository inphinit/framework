<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Config
{
    private static $exceptionlevel = 3;
    private $data = array();
    private $path;

    /**
     * Return items from a config file in a object (iterator or with ->)
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return void
     */
    public function __construct($path)
    {
        $this->path = 'application/Config/' . str_replace('.', '/', $path) . '.php';

        $this->reload();
    }

    /**
     * Create a Negotiation instance
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return \Inphinit\Config
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
     * @throws \Inphinit\Exception
     * @return \Inphinit\Config
     */
    public function reload()
    {
        $level = self::$exceptionlevel;

        self::$exceptionlevel = 2;

        if (File::exists(INPHINIT_SYSTEM . '/' . $this->path) === false) {
            throw new Exception('File not found ' . $this->path, 0, $level);
        }

        foreach (\inphinit_sandbox($this->path) as $key => $value) {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Save configuration to file
     *
     * @return bool
     */
    public function save()
    {
        $path = INPHINIT_SYSTEM . '/' . $this->path;
        $contents = "<?php\nreturn " . var_export($this->data, true) . ";\n";

        return file_put_contents($path, $contents, LOCK_EX) !== false;
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
     * @return \Inphinit\Config
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

    public function __destruct()
    {
        $this->data = null;
    }
}
