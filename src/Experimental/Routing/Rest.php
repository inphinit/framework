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
     * @param string $namecontroller
     * @return \Inphinit\Experimental\Routing\Rest
     */
    public static function create($namecontroller)
    {
        return new static($namecontroller);
    }

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $namecontroller
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($namecontroller)
    {
        $controller = parent::$prefixNS . strtr($namecontroller, '.', '\\');
        $fc = '\\Controller\\' . $controller;

        if (class_exists($fc) === false) {
            throw new Exception('Invalid class ' . $fc, 2);
        }

        $this->valids = array(
            'index'   => array( 'GET',  '/' ),
            'create'  => array( 'GET',  '/create' ),
            'store'   => array( 'POST', '/' ),
            'show'    => array( 'GET',  're:#^/([a-z0-9_\-]+)$#i' ),
            'edit'    => array( 'GET',  're:#^/([a-z0-9_\-]+)/edit$#i' ),
            'update'  => array( array('PUT', 'PATCH'), 're:#^/([a-z0-9_\-]+)$#i' ),
            'destroy' => array( 'DELETE', 're:#^/([a-z0-9_\-]+)$#i' ),
        );

        $this->controller = $namecontroller;
        $this->fullController = $fc;

        $allowedMethods = array_keys($this->valids);

        $this->classMethods = array_intersect(get_class_methods($fc), $allowedMethods);

        App::on('init', array($this, 'prepare'));
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

        $controller   = $this->controller;
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
