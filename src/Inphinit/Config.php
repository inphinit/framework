<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Utility\Others;

class Config
{
    private static $exceptionLevel = 2;
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
        $this->path = 'configs/' . str_replace('.', '/', $path) . '.php';

        $this->reload();
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
        $level = self::$exceptionLevel;

        self::$exceptionLevel = 2;

        $configs = inphinit_sandbox($this->path);

        if (!$configs) {
            throw new Exception($this->path . ' configurations cannot be loaded', 0, $level);
        }

        if (is_array($configs) === false) {
            throw new Exception($this->path . ' has invalid data', 0, $level);
        }

        foreach ($configs as $key => $value) {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Save configuration to file
     *
     * @return bool
     */
    public function commit()
    {
        $path = INPHINIT_SYSTEM . '/' . $this->path;
        $contents = "<?php\nreturn " . var_export($this->data, true) . ";\n";

        return file_put_contents($path, $contents, LOCK_EX) !== false;
    }

    /**
     * Magic method for get specific item by ->
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
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
