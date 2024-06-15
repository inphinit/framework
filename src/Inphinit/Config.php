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
     * @return void
     */
    public function reload()
    {
        $level = self::$exceptionLevel;

        self::$exceptionLevel = 2;

        $configs = inphinit_sandbox($this->path);

        if (!$configs || is_array($configs) === false) {
            throw new Exception($this->path . ' configurations cannot be loaded or format is invalid', 0, $level);
        }

        foreach ($configs as $key => $value) {
            $this->data[$key] = $value;
        }
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
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
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
