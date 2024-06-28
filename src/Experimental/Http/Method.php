<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Http\Request;

class Method
{
    private static $originalMethod;

    private $methods = array('delete', 'patch', 'put');

    private $headers = array('x-http-method-override', 'x-http-method', 'x-method-override');

    private $params = array('_method', '_HttpMethod');

    /**
     * Create instace
     *
     * @param array $methods Sets allowed methods
     * @param array $headers Sets allowed headers
     * @param array $params  Sets allowed params (GET or POST)
     */
    public function __construct(array $methods = array(), array $headers = array(), array $params = array())
    {
        static::original(); // Save original method

        if ($methods) {
            $this->methods = $methods;
        }

        if ($headers) {
            $this->headers = $headers;
        }

        if ($params) {
            $this->params = $params;
        }
    }

    /**
     * Get method from headers
     *
     * @param mixed $alternative
     * @return mixed
     */
    public function fromHeaders($alternative = null)
    {
        $method = null;

        foreach ($this->headers as $header) {
            if ($method = Request::header($header)) {
                break;
            }
        }

        return $this->getValue($method, $alternative);
    }

    /**
     * Get method from POST or GET param (using `$_REQUEST`)
     *
     * @param mixed $alternative
     * @return mixed
     */
    public function fromParams($alternative = null)
    {
        $method = null;

        foreach ($this->params as $param) {
            if (isset($_REQUEST[$param]) && $method = $_REQUEST[$param]) {
                break;
            }
        }

        return $this->getValue($method, $alternative);
    }

    /**
     * `$_SERVER['REQUEST_METHOD']` override using default settings
     *
     * @param bool $headers
     * @param bool $params
     */
    public static function override($headers = true, $params = true)
    {
        $instance = new static();

        $method = $headers ? $instance->fromHeaders() : null;

        if ($params && $method === null) {
            $method = $instance->fromParams();
        }

        if ($method !== null) {
            $_SERVER['REQUEST_METHOD'] = $method;
        }
    }

    /**
     * Get original method
     *
     * @return string
     */
    public static function original()
    {
        if (self::$originalMethod === null) {
            self::$originalMethod = $_SERVER['REQUEST_METHOD'];
        }

        return self::$originalMethod;
    }

    private function getValue($method, $alternative)
    {
        if ($method && in_array(strtolower($method), $this->methods)) {
            return strtoupper($method);
        }

        return $alternative;
    }
}
