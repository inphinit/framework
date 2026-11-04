<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Http\Request;

class Method
{
    private $alloweds = array('DELETE', 'PATCH', 'PUT', 'QUERY');
    private $header = 'x-http-method-override';
    private $param = '_method';
    private $server;
    private $post;

    /**
     * Create a Method instance
     *
     * @param array $server
     * @param array $post
     */
    public function __construct(array $server, array $post)
    {
        $this->server = $server;
        $this->post = $post;
    }

    /**
     * Replaces `$_SERVER['REQUEST_METHOD']` with the detected alternative method
     * and create `$_SERVER['REQUEST_METHOD_ORIGINAL']` with original method
     */
    public static function override()
    {
        $instance = new static($_SERVER, $_POST);
        $original = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

        $_SERVER['REQUEST_METHOD'] = (string) $instance;
        $_SERVER['REQUEST_METHOD_ORIGINAL'] = $original;

        $instance = null;
    }

    /**
     * Set which HTTP methods can be used to override the original method
     *
     * @param array $methods
     */
    public function setAllowed(array $methods)
    {
        $this->alloweds = array_map('strtoupper', $methods);
    }

    /**
     * Set the source header to override the HTTP method. Examples:
     * - x-http-method
     * - x-method-override
     *
     * @param string $header
     */
    public function setHeader($header)
    {
        $this->header = $header;
    }

    /**
     * Set the source parameter to override the HTTP method. Examples:
     * - _HttpMethod
     * - _method
     *
     * @param string $param
     */
    public function setParam($param)
    {
        $this->param = $param;
    }

    /**
     * Get method from post param or header
     *
     * @return string
     */
    public function __toString()
    {
        $method = null;
        $server = $this->server;
        $original = isset($server['REQUEST_METHOD']) ? $server['REQUEST_METHOD'] : '';

        if (isset($this->post[$this->param][0]) && $original === 'POST') {
            $method = $this->post[$this->param];
        } else {
            $header = 'HTTP_' . strtoupper(str_replace('-', '_', $this->header));

            if (isset($server[$header][0])) {
                $method = $server[$header];
            }
        }

        if (is_string($method)) {
            $method = strtoupper($method);

            if (in_array($method, $this->alloweds, true)) {
                return $method;
            }
        }

        return $original;
    }
}
