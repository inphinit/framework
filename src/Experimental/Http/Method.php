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

    private $allowed = array('delete', 'patch', 'put');

    private $headers = array('x-http-method-override', 'x-http-method', 'x-method-override');

    private $params = array('_method', '_HttpMethod');

    /**
     * Sets allowed headers for override HTTP method
     *
     * @param array $headers
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    /**
     * Sets allowed params (GET or POST) for override HTTP method
     *
     * @param array $headers
     */
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Get method from headers (using `$_REQUEST`)
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

        return in_array($method, $this->allowed) ? $method : $alternative;
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

        return in_array($method, $this->allowed) ? $method : $alternative;
    }

    /**
     * HTTP method override using default settings
     *
     * @param bool $headers
     * @param bool $params
     */
    public static function override($headers = true, $params = true)
    {
        $instance = new static;

        $method = $headers ? $instance->fromHeaders() : null;

        if ($params && $method === null) {
            $method = $instance->fromParams();
        }

        if ($method !== null) {
            self::original(); // Save original method

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
}
