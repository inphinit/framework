<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

use Inphinit\Exception;

class App extends \Inphinit\App
{
    private static $allowedMethods = array(
        'ANY', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT'
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

        if (count($checkMethods) !== count(array_unique($checkMethods))) {
            throw new Exception('Duplicate methods: ' . implode(', ', $methods));
        }

        $this->checkPatterns($path);

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
     * Validate pattern, if valid create or replace a pattern for URL slugs
     *
     * @param string $name
     * @param string $regex
     * @return void
     */
    public function setPattern($name, $regex)
    {
        if (!$name || is_string($name) === false) {
            throw new Exception('Pattern name is empty or not a string');
        }

        if (!preg_match('#^\w+$#', $name)) {
            throw new Exception('Invalid pattern name: ' . $name);
        }

        if ($regex && preg_match('#' . $regex . '#', '') === false) {
            throw new Exception('"' . $regex . '" pattern causes PCRE: ' . preg_last_error_msg());
        }

        parent::setPattern($name, $regex);
    }

    /**
     * Validate URL pattern, if valid register a callback for isolate routes
     *
     * @param string   $pattern  URI pattern
     * @param \Closure $callback Callback
     */
    public function scope($pattern, \Closure $callback)
    {
        if (!preg_match('#^([a-z*]+)://([^/]+)(\:[\d*]+)?/(.*)/$#', $pattern)) {
            throw new Exception('Invalid match url pattern format, excepeted: {scheme}://{host}:{port}/{path}/ (including wildcard)');
        }

        $this->checkPatterns($pattern);

        parent::scope($pattern, $callback);
    }

    private function checkPatterns($pattern)
    {
        if (strpos($pattern, '<') !== false && preg_match_all('#[<](.*?)(\:(.*?))?[>]#', $pattern, $matches)) {
            $names = $matches[1];

            if (count($names) !== count(array_flip($names))) {
                throw new Exception('There are duplicate named parameters', 0, 3);
            }

            $patterns = array_filter($matches[3]);
            $invalids = array_diff($patterns, array_keys($this->paramPatterns));

            if (count($invalids)) {
                throw new Exception('Invalid patterns: ' . implode(', ', $invalids), 0, 3);
            }
        }
    }
}
