<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Diagnostics\Inspector;

class Config
{
    private $exceptionLevel = 3;
    private $data = array();
    private $path;

    /**
     * Load items from a config file in a object
     *
     * @param string $path
     * @throws \Inphinit\Exception
     */
    public function __construct($path)
    {
        if (preg_match('#^\w+(\.\w[\w\.]+)?$#', $path) !== 1) {
            throw new Exception('Invalid config path');
        }

        $this->path = 'configs/' . str_replace('.', '/', $path) . '.php';

        $this->reload();
    }

    /**
     * Reload configuration from file
     *
     * @throws \Inphinit\Exception
     */
    public function reload()
    {
        $level = $this->exceptionLevel;

        $this->exceptionLevel = 2;

        $configs = inphinit_sandbox($this->path);

        if (is_array($configs) === false) {
            throw new Exception($this->path . ' configurations cannot be loaded or format is invalid', 0, $level);
        }

        $this->data = $configs;
    }

    /**
     * Magic method for get specific item
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Magic method for check if value exists in top-level
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __set($name, $value)
    {
        throw new Exception('Creation of dynamic property is not allowed');
    }
}
