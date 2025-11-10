<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

use Inphinit\Exception;

class App extends \Inphinit\App
{
    private $reflection;
    private static $allowedMethods = array(
        'ANY', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT'
    );

    public function __construct()
    {
        $this->reflection = new \ReflectionClass($this);
    }

    /**
     * Get or set application configs
     *
     * @param string $key
     * @param scalar $value
     * @throws \Inphinit\Exception
     * @return scalar
     */
    public static function config($key, $value = null)
    {
        if (is_string($key) === false || empty($key)) {
            throw new Exception('key expects a non-empty string');
        }

        if ($value !== null && is_scalar($value) === false) {
            throw new Exception('value expects a scalar value');
        }

        return parent::config($key, $value);
    }

    /**
     * Validate method and callback, if valid register callable or controller for a route
     *
     * @param string|array    $methods
     * @param string          $path
     * @param string|callable $callback
     * @throws \Inphinit\Exception
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
            throw new Exception('Invalid method(s): ' . implode(', ', $diffMethods));
        }

        if (count($checkMethods) !== count(array_unique($checkMethods))) {
            throw new Exception('Duplicate methods: ' . implode(', ', $methods));
        }

        $this->checkPatterns($path);

        if (is_string($callback) && strpos($callback, '::') !== false) {
            $controller = '\\Controllers\\' . $this->namespacePrefix . strtok($callback, '::');
            $method = strtok('::');
            $classAndMethod = "{$controller}::{$method}()";

            if (method_exists($controller, $method) === false) {
                throw new Exception($classAndMethod . ' is invalid');
            }

            $reflection = new \ReflectionMethod($controller, $method);

            if ($reflection->isPublic() === false) {
                throw new Exception($classAndMethod . ' is not public');
            }

            if ($reflection->isStatic()) {
                throw new Exception($classAndMethod . ' is static');
            }

            if ($reflection->isConstructor() || $reflection->isDestructor()) {
                throw new Exception($classAndMethod . ' is invalid');
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
     * @throws \Inphinit\Exception
     */
    public function setNamespace($prefix)
    {
        if (strpos($prefix, '\\\\') !== false) {
            throw new Exception("The namespace prefix '{$prefix}' must not contain consecutive backslashes");
        }

        if ($prefix !== '' && preg_match('#^[A-Z][\w\\\\]*$#', $prefix) !== 1) {
            throw new Exception("Invalid namespace prefix: '{$prefix}'");
        }

        parent::setNamespace($prefix);
    }

    /**
     * Validate pattern, if valid create or replace a pattern for URL slugs
     *
     * @param string $name
     * @param string $regex
     * @throws \Inphinit\Exception
     */
    public function setPattern($name, $regex)
    {
        if (!$name || is_string($name) === false) {
            throw new Exception('Pattern name is empty or not a string');
        }

        if (!preg_match('#^\w+$#', $name)) {
            throw new Exception('Invalid pattern name: ' . $name);
        }

        if ($regex && preg_match('#' . $regex . '#', 'sample sample sample') === false) {
            $message = 'The expression "' . $regex . '" has errors';

            $errorDetails = self::regexError();

            if ($errorDetails) {
                $message .= ': ' . $errorDetails;
            }

            throw new Exception($message);
        }

        parent::setPattern($name, $regex);
    }

    /**
     * Validate URL pattern, if valid register a callback for isolate routes
     *
     * @param string   $pattern  URI pattern
     * @param \Closure $callback Callback
     * @throws \Inphinit\Exception
     */
    public function scope($pattern, \Closure $callback)
    {
        if (!preg_match('#^(([a-z*]+)://([^\#?/]+)(\:[\d*]+)?)?(/([^\#?]+)/)?$#', $pattern)) {
            throw new Exception('Invalid match url pattern format, expected: {scheme}://{host}:{port}/{path}/ or /{path}/ (including wildcard)');
        }

        $this->checkPatterns($pattern);

        parent::scope($pattern, $callback);
    }

    public function __get($name)
    {
        $this->checkVisibility($name);

        return parent::__get($name);
    }

    public function __set($name, $value)
    {
        $this->checkVisibility($name);

        parent::__set($name, $value);
    }

    private function checkVisibility($name)
    {
        try {
            $property = $this->reflection->getProperty($name);

            $sourceClass = $property->{'class'};

            if ($property->isPrivate()) {
                $type = 'private';
            } elseif ($property->isProtected()) {
                $type = 'protected';
            } else {
                $type = null;
            }
        } catch (\ReflectionException $e) {
            $type = null;
        }

        if ($type) {
            throw new Exception("Cannot access {$type} property {$sourceClass}::\${$name}", 0, 3);
        }
    }

    private function checkPatterns($pattern)
    {
        if (strpos($pattern, '<') !== false && preg_match_all('#[<](.*?)(\:(.*?))?[>]#', $pattern, $matches)) {
            $bases = $matches[0];
            $names = $matches[1];
            $patterns = $matches[2];

            $j = count($matches[0]);

            for ($i = 0; $i < $j; ++$i) {
                $base = $bases[$i];
                $name = $names[$i];
                $pattern = $patterns[$i];

                // Check invalid parameter names
                if (preg_match('#^[a-z]\w*$#', $name) !== 1) {
                    throw new Exception('Invalid parameter: ' . $base, 0, 3);
                }

                // Check invalid patterns
                if ($pattern !== '' && preg_match('#^\:[a-z]\w*$#', $pattern) !== 1) {
                    throw new Exception('Invalid pattern: ' . $base, 0, 3);
                }
            }

            if (count($names) !== count(array_flip($names))) {
                throw new Exception('There are duplicate named parameters', 0, 3);
            }

            // removes items that do not have defined patterns
            $patterns = array_filter($matches[3]);

            $paramPatterns = array_keys($this->paramPatterns);

            // Compare patterns in scope or routes with paramPatterns
            $invalids = array_diff($patterns, $paramPatterns);

            if (count($invalids)) {
                throw new Exception('Invalid patterns: ' . self::getParamSuggestions($invalids, $paramPatterns), 0, 3);
            }
        }
    }

    private static function regexError()
    {
        if (preg_last_error() === PREG_NO_ERROR) {
            return null;
        }

        if (function_exists('preg_last_error_msg')) {
            return preg_last_error_msg();
        }

        $error = preg_last_error();

        switch ($error) {
            case PREG_NO_ERROR:
                return 'No error';
            case PREG_INTERNAL_ERROR:
                return 'Internal error';
            case PREG_BAD_UTF8_ERROR:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            case PREG_BAD_UTF8_OFFSET_ERROR:
                return 'The offset did not correspond to the beginning of a valid UTF-8 code point';
            case PREG_BACKTRACK_LIMIT_ERROR:
                return 'Backtrack limit exhausted';
            case PREG_RECURSION_LIMIT_ERROR:
                return 'Recursion limit exhausted';
            default:
                if (defined('PREG_JIT_STACKLIMIT_ERROR') && PREG_JIT_STACKLIMIT_ERROR === $error) {
                    return 'JIT stack limit exhausted';
                }
        }

        return 'Unknown error';
    }

    private function getParamSuggestions(array $words, array $suggestions)
    {
        foreach ($words as &$word) {
            $currentDistance = -1;
            $currentSuggestion = null;

            foreach ($suggestions as $suggestion) {
                $distance = levenshtein($word, $suggestion);

                if ($distance < 3 && ($currentDistance === -1 || $distance < $currentDistance)) {
                    $currentDistance = $distance;
                    $currentSuggestion = $suggestion;
                }
            }

            $word = ":{$word}";

            if ($currentSuggestion !== null) {
                $word .= " (suggestion: {$currentSuggestion})";
            }
        }

        return implode(', ', $words);
    }
}
