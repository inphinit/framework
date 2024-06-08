<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Http\Response;
use Inphinit\Viewing\View;

class App
{
    private static $configs;

    private $routes = array();
    private $paramRoutes = array();

    private $namespacePrefix = '';
    private $pathPrefix = '/';

    private $hasParams = false;
    private $paramPatterns = array(
        'alnum' => '[\da-zA-Z]+',
        'alpha' => '[a-zA-Z]+',
        'decimal' => '\d+\.\d+',
        'num' => '\d+',
        'nospace' => '[^/\s]+',
        'uuid' => '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}',
        'version' => '\d+\.\d+(\.\d+(-[\da-zA-Z]+(\.[\da-zA-Z]+)*(\+[\da-zA-Z]+(\.[\da-zA-Z]+)*)?)?)?'
    );

    private $patternNames;

    private $beforeRE = array('\\:', '\\<', '\\>', '\\*');
    private $afterRE = array(':', '<', '>', '.*?');

    public function __construct()
    {
        $this->patternNames = implode('|', array_keys($this->paramPatterns));
    }

    /**
     * Get application configs
     *
     * @param string $key
     * @return mixed
     */
    public static function config($key, $value = null)
    {
        if (self::$configs === null) {
            self::$configs = inphinit_sandbox('configs/app.php');
        }

        if (array_key_exists($key, self::$configs)) {
            if ($value == null) {
                return self::$configs[$key];
            }

            self::$configs[$key] = $value;
        }
    }

    /**
     * Register a callback or script for a route
     *
     * @param string|array    $methods
     * @param string          $path
     * @param string|callable $callback
     * @return void
     */
    public function action($methods, $path, $callback)
    {
        $path = $this->pathPrefix . ltrim($path, '/');

        if (strpos($path, '<') !== false) {
            $routes = &$this->paramRoutes;

            $this->hasParams = true;
        } else {
            $routes = &$this->routes;
        }

        if (isset($routes[$path]) === false) {
            $routes[$path] = array();
        }

        if (is_array($methods)) {
            foreach ($methods as $method) {
                $routes[$path][strtoupper($method)] = $callback;
            }
        } else {
            $routes[$path][strtoupper($methods)] = $callback;
        }
    }

    /**
     * Prefix controller on scope
     *
     * @param string $prefix Set controller prefix
     */
    public function setNamespace($prefix)
    {
        $this->namespacePrefix = $ns;
    }

    /**
     * Prefix route paths on scope
     *
     * @param string $prefix Set path prefix
     */
    public function setPath($prefix)
    {
        $this->pathPrefix = $prefix;
    }

    /**
     * Register a callback for a URI pattern
     *
     * @param string   $pattern  URI pattern
     * @param \Closure $callback Callback
     */
    public function scope($pattern, \Closure $callback)
    {
        $patterns = &$this->paramPatterns;

        $regex = '#[<]([A-Za-z]\w+)(\:(' . $this->patternNames . '))?[>]#';

        $pattern = str_replace($this->beforeRE, $this->afterRE, preg_quote($pattern));

        $pattern = preg_replace_callback($regex, function ($matches) use (&$patterns) {
            return '(?P<' . $matches[1] . '>' . (
                isset($matches[3]) ? $patterns[$matches[3]] : '[^/]+'
            ) . ')';
        }, $pattern);

        if (preg_match('#^' . $pattern . '#', INPHINIT_URL, $params)) {
            $path = parse_url($params[0], PHP_URL_PATH);

            if ($path) {
                $this->pathPrefix = $path;
            }

            foreach ($params as $index => $value) {
                if (is_int($index)) {
                    unset($params[$index]);
                }
            }

            $callback($this, $params);

            $this->namespacePrefix = '';
            $this->pathPrefix = '/';
        }
    }

    /**
     * Execute application
     *
     * @return bool Returns false if request matches a file in built-in web server, otherwise returns true
     */
    public function exec()
    {
        $code = self::$configs['maintenance'] ? 503 : http_response_code();
        $callback = null;
        $params = null;
        $output = null;

        if ($code === 200) {
            if (PHP_SAPI === 'cli-server' && $this->fileInBuiltIn()) {
                return false;
            }

            $path = INPHINIT_PATH;
            $method = $_SERVER['REQUEST_METHOD'];

            if (isset($this->routes[$path])) {
                $routes = &$this->routes[$path];

                if (isset($routes[$method])) {
                    $callback = $routes[$method];
                } elseif (isset($routes['ANY'])) {
                    $callback = $routes['ANY'];
                } else {
                    $code = 405;
                }
            } elseif ($this->hasParams) {
                $this->params($method, $code, $callback, $params);
            } else {
                $code = 404;
            }
        }

        if ($code === 200) {
            if (is_string($callback) && strpos($callback, '::') !== false) {
                $parsed = explode('::', $callback, 2);
                $callback = '\\Controller\\' . $this->namespacePrefix . $parsed[0];
                $callback = array(new $callback, $parsed[1]);
            }

            $output = $callback($params);
        } else {
            http_response_code($code);
            $code = array('status' => $code);
            inphinit_sandbox('errors.php', $code);
        }

        self::forward($output);

        return true;
    }

    /**
     * Create or remove a pattern for URL slugs
     *
     * @param string $pattern Set pattern for URL slug params like this /foo/<var:pattern>
     * @return void
     */
    public function setPattern($pattern, $regex)
    {
        $this->paramPatterns[preg_quote($pattern)] = $regex;
        $this->patternNames = implode('|', array_keys($this->paramPatterns));
    }

    /**
     * Get routes from current scope and parents
     *
     * @return array
     */
    public function routes()
    {
        return $this->routes + $this->paramRoutes;
    }

    public static function forward(&$output = null)
    {
        if (class_exists('\\Inphinit\\Viewing\\View', false)) {
            View::dispatch();
        }

        echo $output;

        if (class_exists('\\Inphinit\\Event', false)) {
            Event::trigger('done');
        }
    }

    private function params($method, &$code, &$callback, &$params)
    {
        $code = 404;
        $patterns = &$this->paramPatterns;
        $getParams = '#\\\\[<]([A-Za-z]\\w+)(\\\\:(' . $this->patternNames . ')|)\\\\[>]#';

        $limit = 20;
        $total = count($this->paramRoutes);

        for ($indexRoutes = 0; $indexRoutes < $total; $indexRoutes += $limit) {
            $slice = array_slice($this->paramRoutes, $indexRoutes, $limit);

            $j = 0;
            $callbacks = array();

            foreach ($slice as $regexPath => &$param) {
                $callbacks[] = $param;
                $param = '#route_' . (++$j) . '>' . preg_quote($regexPath);
            }

            $groupRegex = implode(')|(', $slice);
            $groupRegex = preg_replace($getParams, '(?<$1><$3>)', $groupRegex);
            $groupRegex = str_replace('<>)', '[^/]+)', $groupRegex);

            foreach ($patterns as $pattern => $regex) {
                $groupRegex = str_replace('<' . $pattern . '>)', $regex . ')', $groupRegex);
            }

            $groupRegex = str_replace('#route_', '?<route_', $groupRegex);

            if (preg_match('#^((?J)(' . $groupRegex . '))$#', INPHINIT_PATH, $params)) {
                foreach ($params as $index => $value) {
                    if ($value === '' || is_int($index)) {
                        unset($params[$index]);
                    } else if (strpos($index, 'route_') === 0) {
                        $callbacks = $callbacks[substr($index, 6) - 1];
                        unset($params[$index]);
                    }
                }

                $code = 200;

                if (isset($callbacks[$method])) {
                    $callback = $callbacks[$method];
                } elseif (isset($callbacks['ANY'])) {
                    $callback = $callbacks['ANY'];
                } else {
                    $code = 405;
                }

                break;
            }
        }
    }

    private function fileInBuiltIn()
    {
        $path = INPHINIT_PATH;

        return (
            $path !== '/' &&
            strpos($path, '.') !== 0 &&
            strpos($path, '/.') === false &&
            is_file(INPHINIT_ROOT . '/public' . $path)
        );
    }
}
