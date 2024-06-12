<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

use Inphinit\App;
use Inphinit\Exception;

class DebugApp extends App
{
    private static $allowedMethods = array(
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'ANY'
    );

    /**
     * Validate method and callback, if valid register callable or controller for a route
     *
     * @param string|array    $methods
     * @param string          $path
     * @param string|callable $callback
     * @throws \Inphinit\Exception
     * @return void
     */
    public function action($methods, $path, $callback)
    {
        $checkMethods = is_array($methods) ? $methods : array($methods);

        foreach ($checkMethods as $method) {
            if (is_string($method) === false) {
                throw new Exception('One of the methods is not a string');
            }
        }

        $diffMethods = array_diff($checkMethods, self::$allowedMethods);

        if ($diffMethods) {
            throw new Exception('Invalid methods: ' . implode(', ', $diffMethods));
        }

        if (is_string($callback) && strpos($callback, '::') !== false) {
            list($className, $method) = explode('::', $callback);

            $className = '\\Controller\\' . $className;
            $classAndMethod = "{$className}::{$method}()";

            if (method_exists($className, $method) === false) {
                throw new Exception($classAndMethod . ' is invalid');
            }

            $reflection = new \ReflectionMethod($className, $method);

            if ($reflection->isPublic() === false) {
                throw new Exception($classAndMethod . ' is not public');
            }

            if ($reflection->isStatic()) {
                throw new Exception($classAndMethod . ' is static');
            }

            if ($reflection->isConstructor() || $reflection->isDestructor()) {
                throw new Exception($classAndMethod . ' is not valid');
            }
        } elseif (is_callable($callback) === false) {
            throw new Exception('Callback is not callable');
        }

        parent::action($methods, $path, $callback);
    }

    /**
     * Validate namespace prefix, if valid define controller prefix on scope
     *
     * @param string $prefix Set controller prefix
     */
    public function setNamespace($prefix)
    {
        if (substr($prefix, -1) !== '\\' || $prefix[0] !== '\\' || strpos($prefix, '\\\\') !== false) {
            throw new Exception($prefix . ' controller prefix is not valid');
        }

        parent::setNamespace($prefix);
    }

    /**
     * Validate path prefix, if valid define route prefix paths on scope
     *
     * @param string $prefix Set path prefix
     */
    public function setPath($prefix)
    {
        if (substr($prefix, -1) !== '/' || $prefix[0] !== '/' || strpos($prefix, '/') !== false) {
            throw new Exception($prefix . ' path prefix is not valid');
        }

        parent::setPath($prefix);
    }

    /**
     * Validate pattern, if valid create or remove a pattern for URL slugs
     *
     * @param string $pattern
     * @return void
     */
    public function setPattern($pattern, $regex)
    {
        if (!$pattern || is_string($pattern) === false) {
            throw new Exception('Invalid pattern');
        }

        if ($regex && preg_match('#' . $regex . '#', '') === false) {
            throw new Exception('"' . $regex . '" pattern causes PCRE: ' . preg_last_error_msg());
        }

        parent::setPattern($pattern, $regex);
    }

    /**
     * Validate pattern, if valid register a callback for isolate routes
     *
     * @param string   $pattern  URI pattern
     * @param \Closure $callback Callback
     */
    public function scope($pattern, \Closure $callback)
    {
        if (!preg_match("#^([a-z*]+)://([^/]+)(\:[\d*]+)?/(.*)/#", $pattern)) {
            throw new Exception('Invalid match url pattern format, excepeted: <scheme>://<host>:<port>/<path>/ (including wildcard)');
        }

        parent::scope($pattern, $callback);
    }
}
