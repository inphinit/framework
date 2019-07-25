<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */
namespace Inphinit\Experimental\Routing;

use Inphinit\App;
use Inphinit\Routing\Route;
use Inphinit\Routing\Router;
use Inphinit\Experimental\Exception;

class Resource extends Router
{
    private $controller;
    private $fullController;
    private $ready = false;
    private static $valids = array(
        'index'   => array( 'GET',  '/' ),
        'create'  => array( 'GET',  '/create' ),
        'store'   => array( 'POST', '/' ),
        'show'    => array( 'GET',  '/{:[^/]+:}' ),
        'edit'    => array( 'GET',  '/{:[^/]+:}/edit' ),
        'update'  => array( 'POST', '/{:[^/]+:}/update' ),
        'destroy' => array( 'POST', '/{:[^/]+:}/destroy' ),
    );

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @param string $path
     * @return void
     */
    public static function create($controller, $path = null)
    {
        $rest = new static($controller, $path);
        $rest->prepare();
        $rest = null;
    }

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($controller, $path = null)
    {
        $fullController = parent::$prefixNS . strtr($controller, '.', '\\');
        $fullController = '\\Controller\\' . $fullController;

        if (class_exists($fullController) === false) {
            throw new Exception('Invalid class ' . $fullController, 2);
        }

        $this->controller = $controller;
        $this->fullController = $fullController;

        $this->path = $path !== null ? $path : strtolower('/' . parent::$prefixNS . strtr($controller, '.', '/'));
    }

    /**
     * Define routes
     *
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function prepare()
    {
        if ($this->ready) {
            return null;
        }

        $this->ready = true;

        $controller = $this->fullController;

        $methods = get_class_methods($controller);
        $allowedMethods = array_keys(self::$valids);

        $classMethods = array_intersect($methods, $allowedMethods);

        if (empty($classMethods)) {
            throw new Exception($controller . ' controller exists, but is not a valid', 2);
        }

        foreach ($classMethods as $method) {
            $route = empty(self::$valids[$method]) ? false : self::$valids[$method];

            if ($route) {
                Route::set($route[0], $this->path.$route[1], function () use ($method, $controller) {
                    header('Content-Type: text/html; charset=UTF-8');

                    return call_user_func_array(array(new $controller, $method), func_get_args());
                });
            }
        }
    }
}