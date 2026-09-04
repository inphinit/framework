<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

use Inphinit\App;
use Inphinit\Exception;

abstract class Treaty
{
    /** @var int Create a route with slash at the end, like: `/foo/` */
    const SLASH = 1;

    /** @var int Create a route without slash at the end, like: `/foo` */
    const NOSLASH = 2;

    /** @var string[] HTTP verbs accepted as method name prefixes (lowercase) */
    protected static $allowedMethods = array(
        'any', 'delete', 'get', 'head', 'options', 'patch', 'post', 'put'
    );

    private $modes;
    private $context;

    /**
     * Create routes basead in a Controller or other Class
     *
     * @param \Inphinit\App $context
     * @param int $modes
     * @throws \Inphinit\Exception
     */
    public function __construct(App $context, $modes)
    {
        $valid_modes = self::SLASH | self::NOSLASH;

        if (is_int($modes) === false || ($modes & ~$valid_modes) !== 0) {
            throw new Exception('Invalid route path modes');
        }

        $this->modes = $modes;

        $this->context = $context;
    }

    /**
     * Scans public instance methods and registers matching ones as routes.
     * Returns true if at least one route was registered, false otherwise.
     *
     * @return bool
     */
    public function mount()
    {
        $valid = false;
        $reflect = new \ReflectionClass($this);
        $pattern = sprintf('#^(%s)([A-Z0-9].*?)$#', implode('|', static::$allowedMethods));

        foreach ($reflect->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $callback = $method->getName();

            if ($method->isStatic() === false && preg_match($pattern, $callback, $match)) {
                $this->bindRoute($match[1], $match[2], $callback);
                $valid = true;
            }
        }

        return $valid;
    }

    /**
     * Instantiates the controller and dispatches its routes against the global $app.
     * Intended as a one-line convenience call from route definition files.
     * Throws if the global $app is unavailable or no routes are found.
     *
     * @global \Inphinit\App $app
     * @param int            $modes
     * @throws \Inphinit\Exception
     */
    public static function dispatch($modes = 0)
    {
        global $app;

        if (($app instanceof App) === false) {
            throw new Exception('The global route system was not found');
        }

        if ($modes === 0) {
            $modes = self::SLASH | self::NOSLASH;
        }

        $instance = new static($app, $modes);

        if ($instance->mount() === false) {
            throw new Exception('This class does not have methods that can be converted into routes');
        }
    }

    /**
     * Converts a class method suffix to a kebab-case URL segment.
     * Examples:
     *   - `public function postFooBarBaz()` becomes `foo-bar-baz`.
     *   - `public function getUserID()` becomes `user-id`.
     * Note: Override this method to customize path formatting.
     *
     * @param string $path
     * @return string
     */
    protected static function formatPath($path)
    {
        return strtolower(preg_replace('#([a-z0-9])([A-Z]+)#', '$1-$2', $path));
    }

    private function bindRoute($method, $path, $callback)
    {
        $method = strtoupper($method);
        $callback = array($this, $callback);

        if ($path === 'Index') {
            $path = '/';
        } else {
            $path = '/' . static::formatPath($path);
        }

        if ($this->modes & self::NOSLASH) {
            $this->context->action($method, $path, $callback);
        }

        if ($path !== '/' && ($this->modes & self::SLASH)) {
            $this->context->action($method, $path . '/', $callback);
        }
    }
}
