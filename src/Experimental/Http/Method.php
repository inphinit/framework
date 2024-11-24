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
    private $allowed = array('delete', 'patch', 'put');

    private $sources = array(
        array('x-http-method-override', true, 0),
        array('x-http-method', true, 0),
        array('x-method-override', true, 0),
        array('_method', false, 0),
        array('_HttpMethod', false, 0),
    );

    /**
     * Create instace
     *
     * @param bool $reset Reset sources
     */
    public function __construct($reset = false)
    {
        if ($reset) {
            $this->sources = array();
        }
    }

    /**
     * Set allowed
     *
     * @param array $methods
     */
    public function setAllowed(array $methods)
    {
        $this->allowed = $methods;
    }

    /**
     * Append header
     *
     * @param bool $header
     * @param int  $priority
     */
    public function appendHeader($header, $priority = 0)
    {
        $this->sources[] = array($header, true, $priority);
    }

    /**
     * Append param
     *
     * @param bool $param
     * @param int  $priority
     */
    public function appendParam($param, $priority = 0)
    {
        $this->sources[] = array($param, false, $priority);
    }

    /**
     * Get header from `$_REQUEST` or from headers
     *
     * @return string
     */
    public function __toString()
    {
        usort($this->sources, function ($a, $b) {
            if ($a[2] === $b[2]) {
                return 0;
            }

            return $a[2] > $b[2] ? 1 : -1;
        });

        $method = null;

        foreach ($this->sources as $source) {
            $key = $source[0];

            if ($source[1]) {
                $method = Request::header($key);
            } elseif (isset($_REQUEST[$key])) {
                $method = $_REQUEST[$key];
            }

            if ($method && in_array(strtolower($method), $this->allowed)) {
                return $method;
            }
        }

        return '';
    }

    /**
     * Override `$_SERVER['REQUEST_METHOD']`
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
}
