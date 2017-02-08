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
    private $controller;
    private $fullController;
    private $classMethods = array();
    private $valids;
    private $ready = false;

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @return \Inphinit\Experimental\Routing\Rest
     */
    public static function create($controller)
    {
        return new static($controller);
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
        $fullcontroller = parent::$prefixNS . strtr($controller, '.', '\\');
        $fullcontroller = '\\Controller\\' . $fullcontroller;

        if (class_exists($fullcontroller) === false) {
            throw new Exception('Invalid class ' . $fullcontroller, 2);
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
        $this->fullController = $fullcontroller;

        $allowedMethods = array_keys($this->valids);

        $this->classMethods = array_intersect(get_class_methods($fullcontroller), $allowedMethods);
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
            return false;
        }

        $this->ready = true;

        if (empty($this->classMethods)) {
            throw new Exception($this->fullController . ' is empty ', 2);
        }

        $controller = $this->controller;
        $classMethods = $this->classMethods;

        foreach ($classMethods as $value) {
            $route = $this->getRoute($value);

            if ($route) {
                Route::set($route[0], $route[1], $controller . ':' . $value);
            }
        }
    }

    /**
     * Check if method class is valid for REST
     *
     * @param string $methodName
     * @return array|bool
     */
    private function getRoute($methodName)
    {
        if (empty($this->valids[$methodName])) {
            return false;
        }

        return $this->valids[$methodName];
    }
}
