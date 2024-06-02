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
    public function __construct()
    {
        /* keep empty */
    }

    public function action($methods, $path, $callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            list($className, $method) = explode('::', $callback);

            $className = '\\Controller\\' . $className;
            $classAndMethod = "{$className}::{$method}()";

            if (method_exists($className, $method) === false) {
                throw new Exception($classAndMethod . ' is invalid', 0, 2);
            }

            $reflection = new \ReflectionMethod($className, $method);

            if ($reflection->isPublic() === false) {
                throw new Exception($classAndMethod . ' is not public', 0, 2);
            }

            if ($reflection->isStatic()) {
                throw new Exception($classAndMethod . ' is static', 0, 2);
            }

            if ($reflection->isConstructor() || $reflection->isDestructor()) {
                throw new Exception("{$classAndMethod} is not valid", 0, 2);
            }
        } elseif (is_callable($callback) === false) {
            throw new Exception('Callback is not callable', 0, 2);
        }

        parent::action($methods, $path, $callback);
    }

    public function setPattern($pattern, $regex)
    {
        if (!$pattern) {
            throw new Exception('Invalid pattern', 0, 2);
        }

        if ($regex && preg_match('#' . $regex . '#', '') === false) {
            throw new Exception('Invalid regex pattern', 0, 2);
        }

        parent::setPattern($pattern, $regex);
    }

    public function setNamespace($prefix)
    {
        if (substr($prefix, -1) !== '\\' || $prefix[0] !== '\\' || strpos($prefix, '\\\\') !== false) {
            throw new Exception($prefix . ' controller prefix is not valid', 0, 2);
        }

        parent::setNamespace($prefix);
    }

    public function setPath($prefix)
    {
        if (substr($prefix, -1) !== '/' || $prefix[0] !== '/' || strpos($prefix, '/') !== false) {
            throw new Exception($prefix . ' path prefix is not valid', 0, 2);
        }

        parent::setPath($prefix);
    }

    public function scope($pattern, \Closure $callback)
    {
        if (!preg_match("#^(\*|\S+)://(\*|\S+\:(\*|\d+))/#", $pattern)) {
            throw new Exception('Invalid match url pattern format, excepeted: <scheme>://<host>:<port>/<path> or <scheme>://*/', 0, 2);
        }

        parent::scope($pattern, $callback);
    }
}
