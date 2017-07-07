<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Routing;

use Inphinit\App;
use Inphinit\Routing\Route;
use Inphinit\Routing\Router;
use Inphinit\Experimental\Exception;

class Rest extends Router
{
    private $contentType = 'application/json';
    private $charset = 'UTF-8';
    private $controller;
    private $fullController;
    private $valids;
    private $ready = false;

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @return void
     */
    public static function create($controller)
    {
        $rest = new static($controller);
        $rest->prepare();
        $rest = null;
    }

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($controller)
    {
        $fullController = parent::$prefixNS . strtr($controller, '.', '\\');
        $fullController = '\\Controller\\' . $fullController;

        if (class_exists($fullController) === false) {
            throw new Exception('Invalid class ' . $fullController, 2);
        }

        $this->valids = array(
            'index'   => array( 'GET',  '/' ),
            'create'  => array( 'GET',  '/create' ),
            'store'   => array( 'POST', '/' ),
            'show'    => array( 'GET',  '/{:[^/]+:}' ),
            'edit'    => array( 'GET',  '/{:[^/]+:}/edit' ),
            'update'  => array( array('PUT', 'PATCH'), '/{:[^/]+:}' ),
            'destroy' => array( 'DELETE', '/{:[^/]+:}' ),
        );

        $this->controller = $controller;
        $this->fullController = $fullController;
    }

    /**
     * Define routes
     *
     * @param string $contentType
     * @param string $charset
     * @return void
     */
    public function type($contentType, $charset = 'UTF-8')
    {
        $this->contentType = $contentType;
        $this->charset = $charset;
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
        $allowedMethods = array_keys($this->valids);

        $classMethods = array_intersect($methods, $allowedMethods);

        if (empty($classMethods)) {
            throw new Exception($controller . ' controller exists, but is not a valid', 2);
        }

        $contentType = $this->contentType . '; charset=' . $this->charset;
        $valids = $this->valids;

        $this->valids = null;

        foreach ($classMethods as $method) {
            $route = empty($valids[$method]) ? false : $valids[$method];

            if ($route) {
                Route::set($route[0], $route[1], function ()
                use ($method, $contentType, $controller) {
                    header('Content-Type: ' . $contentType);

                    return call_user_func_array(array(new $controller, $method), func_get_args());
                });
            }
        }
    }
}
