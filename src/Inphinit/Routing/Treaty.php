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

abstract class Treaty
{
    /** @var int Create a route with slash at the end, like: `/foo/` */
    const SLASH = 1;

    /** @var int Create a route without slash at the end, like: `/foo` */
    const NOSLASH = 2;

    /** @var int Define path format, possible values: `self::SLASH`, `self::NOSLASH`, `self::SLASH|self::NOSLASH` */
    protected $format;

    /** @var string Define regex for match public methods from controller */
    protected static $valids = '#^(any|delete|get|head|options|patch|post|put)([A-Z0-9]\w+)$#';

    private $context;

    /**
     * Define routes based on class methods
     *
     * @param \Inphinit\App $context
     * @throws \Inphinit\Exception
     */
    public function route(App $context)
    {
        $this->context = $context;

        $invalid = true;
        $analysis = new \ReflectionClass($this);

        foreach ($analysis->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $callback = $method->getName();

            if ($method->isStatic() === false && preg_match(self::$valids, $callback, $match)) {
                $this->putRoute(strtoupper($match[1]), '/' . $match[2], $callback);
                $invalid = false;
            }
        }

        if ($invalid) {
            throw new \Inphinit\Exception('Invalid controller');
        }
    }

    /**
     * Define routes based on class methods
     *
     * @param \Inphinit\App $context
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public static function action(App $context)
    {
        $instance = new static();
        $instance->route($context);
        return $instance;
    }

    /**
     * Overwrite path parser
     *
     * @param string $path
     * @return string
     */
    protected static function parsePath($path)
    {
        return strtolower(preg_replace('#([a-z0-9])([A-Z])#', '$1-$2', $path));
    }

    private function putRoute($method, $path, $callback)
    {
        $callback = array($this, $callback);

        if ($path === '/Index') {
            $path = '/';
        } else {
            $path = self::parsePath($path);
        }

        if ($this->format) {
            $format = $this->format;
        } else {
            $format = self::SLASH | self::NOSLASH;
        }

        if ($format & self::NOSLASH) {
            $this->context->action($method, $path, $callback);
        }

        if ($path !== '/' && $format & self::SLASH) {
            $this->context->action($method, $path . '/', $callback);
        }
    }
}
