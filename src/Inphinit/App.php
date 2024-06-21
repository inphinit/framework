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
    protected $paramPatterns = array(
        'alnum' => '[\da-zA-Z]+',
        'alpha' => '[a-zA-Z]+',
        'decimal' => '(0|[1-9]\d+)\.\d+',
        'nospace' => '[^/\s]+',
        'num' => '\d+',
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
     * Get or set application configs
     *
     * @param string $key
     * @return mixed
     */
    public static function config($key, $value = null)
    {
        if (self::$configs === null) {
            self::$configs = inphinit_sandbox('configs/app.php');
        }

        if (isset(self::$configs[$key])) {
            if ($value === null) {
                return self::$configs[$key];
            }

            self::$configs[$key] = $value;
        }
    }

    /**
     * Register callable or controller for a route
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
     * Prefixes the namespace in the current scope control
     *
     * @param string $prefix
     * @return void
     */
    public function setNamespace($prefix)
    {
        $this->namespacePrefix = $prefix;
    }

    /**
     * Prefixes route path in the current scope control
     *
     * @param string $prefix
     * @return void
     */
    public function setPath($prefix)
    {
        $this->pathPrefix = $prefix;
    }

    /**
     * Create or replace a pattern for URL slugs
     *
     * @param string $name
     * @param string $regex
     * @return void
     */
    public function setPattern($name, $regex)
    {
        $this->paramPatterns[preg_quote($name)] = $regex;
        $this->patternNames = implode('|', array_keys($this->paramPatterns));
    }

    /**
     * Register a callback for isolate routes
     *
     * @param string   $pattern  URI pattern
     * @param \Closure $callback Callback
     * @return void
     */
    public function scope($pattern, \Closure $callback)
    {
        $patterns = &$this->paramPatterns;

        $getParams = '#[<]([A-Za-z]\w+)(\:(' . $this->patternNames . '))?[>]#';

        $scopeRegex = str_replace($this->beforeRE, $this->afterRE, preg_quote($pattern));

        $scopeRegex = preg_replace_callback($getParams, function ($matches) use (&$patterns) {
            return '(?P<' . $matches[1] . '>' . (
                isset($matches[3]) ? $patterns[$matches[3]] : '[^/]+'
            ) . ')';
        }, $scopeRegex);

        if (preg_match('#^' . $scopeRegex . '#', INPHINIT_URL, $params)) {
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
        $params = null;
        $callback = null;

        if ($code === 200) {
            if (PHP_SAPI === 'cli-server' && $this->fileInBuiltIn()) {
                return false;
            }

            $path = INPHINIT_PATH;
            $method = $_SERVER['REQUEST_METHOD'];
            $routes = null;

            if (isset($this->routes[$path])) {
                $routes = &$this->routes[$path];
            } elseif ($this->hasParams) {
                $this->params($routes, $params);
            }

            if (isset($routes[$method])) {
                $callback = $routes[$method];
            } elseif (isset($routes['ANY'])) {
                $callback = $routes['ANY'];
            } else {
                $code = $routes === null ? 404 : 405;
            }
        }

        if ($code !== 200) {
            Response::status($code);
            $details = array('status' => $code);
            inphinit_sandbox('errors.php', $details);

            $output = null;
        } else {
            if (is_string($callback) && strpos($callback, '::') !== false) {
                $parsed = explode('::', $callback, 2);
                $callback = '\\Controller\\' . $this->namespacePrefix . $parsed[0];
                $callback = array(new $callback, $parsed[1]);
            }

            $output = $callback($params);
        }

        self::forward($output);

        return true;
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

    private function params(&$routes, &$params)
    {
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
            $groupRegex = preg_replace($getParams, '(?P<$1><$3>)', $groupRegex);
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
                        $routes = $callbacks[substr($index, 6) - 1];
                        unset($params[$index]);
                    }
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
