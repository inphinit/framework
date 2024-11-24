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
        array('x-http-method-override', false, 0),
        array('x-http-method', false, 0),
        array('x-method-override', false, 0),
        array('_method', true, 0),
        array('_HttpMethod', true, 0),
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
     * Get header from $_REUUEST or headers
     *
     * @return string
     */
    public function __toString()
    {
        usort($this->sources, function ($a, $b) {
            if ($a[1] === $b[1]) {
                return 0;
            }

            return $a[1] > $b[1] ? 1 : -1;
        });

        $method = null;

        foreach ($this->sources as $source) {
            if ($source[1]) {
                $method = Request::header($source);
            } elseif (isset($_REQUEST[$source][0])) {
                $method = $_REQUEST[$source][0];
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
