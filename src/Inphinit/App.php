<?php
/**
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Http\Response;
use Inphinit\Viewing\View;

class App
{
    protected $namespacePrefix = '\\Controllers\\';
    protected $pathPrefix = '/';
    protected $filters = array();
    protected $paramPatterns = array(
        'alnum' => '[\da-zA-Z]+',
        'alpha' => '[a-zA-Z]+',
        'decimal' => '(0|[1-9]\d*)\.\d+',
        'nospace' => '[^/\s]+',
        'num' => '\d+',
        'uuid' => '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}',
        'version' => '(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
    );

    private $patternNames;
    private $data = array();
    private $routes = array();
    private $paramRoutes = array();
    private $hasParams = false;

    private static $beforeRE = array('\\:', '\\<', '\\>', '\\*\\*', '\\*');
    private static $afterRE = array(':', '<', '>', '.*?', '[^/]*?');

    /**
     * Get application configs from `$_ENV` with APP_ prefix key
     *
     * @param string $name
     * @return scalar
     */
    public static function config($name)
    {
        $name = 'APP_' . strtoupper($name);
        return isset($_ENV[$name]) && $_ENV[$name] !== '' ? $_ENV[$name] : null;
    }

    /**
     * Register callable or controller for a route
     *
     * @param string|array    $methods
     * @param string          $path
     * @param string|callable $callback
     */
    public function action($methods, $path, $callback)
    {
        $path = $this->pathPrefix . ltrim($path, '/');

        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = $this->namespacePrefix . $callback;
        }

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
     */
    public function setNamespace($prefix)
    {
        $this->namespacePrefix = '\\' . $prefix . '\\';
    }

    /**
     * Add a filter for routes in the current scope
     *
     * @param callable $callback
     */
    public function useFilter(callable $callback)
    {
        $this->filters[] = $callback;
    }

    /**
     * Create or replace a pattern for URL slugs
     *
     * @param string $name
     * @param string $regex
     */
    public function setPattern($name, $regex)
    {
        $this->paramPatterns[preg_quote($name)] = $regex;
        $this->patternNames = null;
    }

    /**
     * Groups routes within the scope of the defined URI
     *
     * @param string   $pattern  URI pattern
     * @param \Closure $callback Callback
     */
    public function scope($pattern, \Closure $callback)
    {
        $this->refreshPatterns();

        $patterns = &$this->paramPatterns;

        $getParams = '#[<]([A-Za-z]\w+)(\:(' . $this->patternNames . '))?[>]#';

        $scopeRegex = str_replace(self::$beforeRE, self::$afterRE, preg_quote($pattern));

        $scopeRegex = preg_replace_callback($getParams, function ($matches) use (&$patterns) {
            return '(?P<' . $matches[1] . '>' . (
                isset($matches[3]) ? $patterns[$matches[3]] : '[^/]+'
            ) . ')';
        }, $scopeRegex);

        $full = $pattern[0] !== '/';
        $subject = $full ? (INPHINIT_URL . INPHINIT_PATH) : INPHINIT_PATH;

        if (preg_match('#^' . $scopeRegex . '#', $subject, $params)) {
            $path = $full ? substr($params[0], strlen(INPHINIT_URL)) : $params[0];

            if ($path) {
                $this->pathPrefix = $path;
            }

            foreach ($params as $index => $value) {
                if (is_int($index)) {
                    unset($params[$index]);
                }
            }

            $previousFilters = $this->filters;
            $previousNamespacePrefix = $this->namespacePrefix;

            $callback($this, $params);

            $this->filters = $previousFilters;
            $this->namespacePrefix = $previousNamespacePrefix;
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
        $code = self::maintenance() ? 503 : http_response_code();
        $params = null;
        $callback = null;
        $output = null;

        if ($code === 200) {
            if (PHP_SAPI === 'cli-server' && (include __DIR__ . '/../public.php')) {
                return false;
            }

            $path = INPHINIT_PATH;
            $method = $_SERVER['REQUEST_METHOD'];
            $routes = null;

            if (isset($this->routes[$path])) {
                $routes = &$this->routes[$path];
            } elseif ($this->hasParams) {
                $this->routesMatch($routes, $params);
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
            inphinit_sandbox('errors.php', array('code' => $code));
        } else {
            if (is_string($callback) && strpos($callback, '::') !== false) {
                list($controller, $methodCtrl) = explode('::', $callback, 2);
                $callback = array(new $controller(), $methodCtrl);
            }

            if (empty($this->filters) === false) {
                foreach ($this->filters as $filter) {
                    if ($filter($this, $method, $path, $params) === false) {
                        $callback = null;
                        break;
                    }
                }
            }

            if ($callback) {
                $output = $callback($this, $params);
            }
        }

        if (class_exists('\\Inphinit\\Viewing\\View', false)) {
            View::dispatch();
        }

        echo $output;

        if (class_exists('\\Inphinit\\Event', false)) {
            Event::trigger('done');
        }

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

    /**
     * Checks if the application is in maintenance mode.
     * Note: the result is affected by the main event when maintenance mode is active.
     *
     * @return bool
     */
    public static function maintenance()
    {
        return is_file(INPHINIT_MAINTENANCE) && Event::trigger('maintenance') !== Event::TRIGGER_STOPPED;
    }

    /**
     * Put the application into maintenance mode
     *
     * @return bool
     */
    public static function down()
    {
        return touch(INPHINIT_MAINTENANCE);
    }

    /**
     * Bring the application out of maintenance mode
     *
     * @return bool
     */
    public static function up()
    {
        return is_file(INPHINIT_MAINTENANCE) === false || unlink(INPHINIT_MAINTENANCE);
    }

    public function __get($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    private function routesMatch(&$routes, &$params)
    {
        $this->refreshPatterns();

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
                    } elseif (strpos($index, 'route_') === 0) {
                        $routes = $callbacks[substr($index, 6) - 1];
                        unset($params[$index]);
                    }
                }

                break;
            }
        }
    }

    private function refreshPatterns()
    {
        if ($this->patternNames === null) {
            $this->patternNames = implode('|', array_keys($this->paramPatterns));
        }
    }
}
