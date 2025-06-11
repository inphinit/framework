<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Http\Request;

class Method
{
    private static $initial;

    private $allowed = array('delete', 'patch', 'put');

    private $sources = array(
        // Headers
        array('x-http-method-override', true, 0),
        array('x-http-method', true, 0),
        array('x-method-override', true, 0),

        // fields or querystring
        array('_method', false, 0),
        array('_HttpMethod', false, 0),
    );

    /**
     * Create a Method instace
     *
     * @param bool $reset Optional. Reset sources
     */
    public function __construct($reset = false)
    {
        self::original();

        if ($reset) {
            $this->sources = array();
        }
    }

    /**
     * Defines which HTTP methods can be used to override the original method
     *
     * @param array $methods
     */
    public function setAllowed(array $methods)
    {
        $this->allowed = array_map('strtolower', $methods);
    }

    /**
     * Adds a request header as an alternative source for determining the HTTP method
     *
     * @param bool $header
     * @param int  $priority
     */
    public function addHeader($header, $priority = 0)
    {
        $this->sources[] = array($header, true, $priority);
    }

    /**
     * Adds a parameter (request body or querystring) as an alternative source for determining the HTTP method
     *
     * @param bool $param
     * @param int  $priority
     */
    public function addParam($param, $priority = 0)
    {
        $this->sources[] = array($param, false, $priority);
    }

    /**
     * Get method from `$_POST` or `$_GET` or headers
     *
     * @return string
     */
    public function __toString()
    {
        usort($this->sources, function ($a, $b) {
            if ($a[2] === $b[2]) {
                return 0;
            }

            return $a[2] < $b[2] ? 1 : -1;
        });

        $method = null;

        foreach ($this->sources as $source) {
            $key = $source[0];

            if ($source[1]) {
                $method = Request::header($key);
            } elseif (isset($_POST[$key])) {
                $method = $_POST[$key];
            } elseif (isset($_GET[$key])) {
                $method = $_GET[$key];
            }

            if ($method && in_array(strtolower($method), $this->allowed)) {
                return strtoupper($method);
            }
        }

        return '';
    }

    /**
     * Replaces `$_SERVER['REQUEST_METHOD']` with the detected alternative method, using the default settings
     */
    public static function override()
    {
        $instance = new static();

        $method = (string) $instance;

        $instance = null;

        if ($method) {
            $_SERVER['REQUEST_METHOD'] = $method;
        }
    }

    /**
     * Gets the original HTTP method of the request, before any overrides
     *
     * @return string
     */
    public static function original()
    {
        if (self::$initial === null) {
            self::$initial = $_SERVER['REQUEST_METHOD'];
        }

        return self::$initial;
    }
}
