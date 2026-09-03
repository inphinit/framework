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

abstract class Resource
{
    /** @var string Define content-type header */
    protected $contentType = 'text/html; charset=UTF-8';

    /**
     * Define methods and routes
     *
     * @var array<string, array{0: string|array<string>, 1: string, 2: string|null}>
     */
    protected static $routeMapping = array(
        'index'   => array('GET', '/', null),
        'create'  => array('GET', '/create', null),
        'store'   => array('POST', '/', null),
        'edit'    => array('GET', '/<id>/edit', null),
        'show'    => array('GET', '/<id>', null),
        'update'  => array(array('PUT', 'PATCH'), '/<id>', null),
        'destroy' => array('DELETE', '/<id>', null)
    );

    private $context;

    /**
     * Create routes basead in a Controller or other Class
     *
     * @param \Inphinit\App $context
     */
    public function __construct(App $context)
    {
        $this->context = $context;
    }

    /**
     * Registers routes for each resource method implemented by this class.
     * Only methods present in both $valids and the concrete class are registered.
     * Returns true if at least one route was registered.
     *
     * @return bool
     */
    public function mount()
    {
        $valid = false;
        $mapping = static::$routeMapping;
        $allowed = array_keys($mapping);
        $methods = get_class_methods($this);

        foreach (array_intersect($methods, $allowed) as $callback) {
            if (isset($mapping[$callback])) {
                $route = $mapping[$callback];
                $type = isset($route[2]) ? $route[2] : $this->contentType;

                $this->context->action($route[0], $route[1], $this->bindCallback($type, $callback));
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
     * @throws \Inphinit\Exception
     */
    public static function dispatch()
    {
        global $app;

        if (($app instanceof App) === false) {
            throw new Exception('The global route system was not found');
        }

        $instance = new static($app);

        if ($instance->mount() === false) {
            throw new Exception('This class does not have methods that can be converted into routes');
        }
    }

    private function bindCallback($type, $method)
    {
        return function ($app, $params) use ($type, $method) {
            header('Content-Type: ' . $type);

            $callback = array($this, $method);

            return $callback($app, $params);
        };
    }
}
