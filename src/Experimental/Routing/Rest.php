<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Experimental\Routing;

use Inphinit\App;
use Inphinit\Routing\Route;
use Inphinit\Routing\Router;

use Experimental\Exception;

/*
usage:

    use Experimental\Routing\Rest;

    Rest::create('RestControllerClass');

Group:

    Group::create()->path('/foo/')->call(function () {
        Rest::create('RestControllerClass');
    });
*/

class Rest extends Router
{
    private $controller;
    private $fullController;
    private $classMethods = array();
    private $valids;
    private $ready = false;

    public static function create($namecontroller)
    {
        return new static($namecontroller);
    }

    public function __construct($namecontroller)
    {
        $controller = parent::$prefixNS . strtr($namecontroller, '.', '\\');
        $fc = '\\Controller\\' . $controller;

        if (class_exists($fc) === false) {
            Exception::raise('Invalid class ' . $fc, 2);
        }

        $this->valids = array(
            'index'   => array( 'GET', '/' ),
            'create'  => array( 'GET', '/create' ),
            'store'   => array( 'POST', '/' ),
            'show'    => array( 'GET', 're:#^/([a-z0-9_\-]+)$#i' ),
            'edit'    => array( 'GET', 're:#^/([a-z0-9_\-]+)/edit$#i' ),
            'update'  => array( array('PUT', 'PATCH'), 're:#^/([a-z0-9_\-]+)$#i' ),
            'destroy' => array( 'DELETE', 're:#^/([a-z0-9_\-]+)$#i' ),
        );

        $this->controller = $namecontroller;
        $this->fullController = $fc;

        $allowedMethods = array_keys($this->valids);

        $this->classMethods = array_intersect(get_class_methods($fc), $allowedMethods);

        App::on('init', array($this, 'prepare'));
    }

    public function prepare()
    {
        if ($this->ready) {
            return null;
        }

        $this->ready = true;

        if (empty($this->classMethods)) {
            Exception::raise($this->fullController . ' is empty ', 2);
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

    private function getRoute($methodName)
    {
        if (empty($this->valids[$methodName])) {
            return false;
        }

        return $this->valids[$methodName];
    }
}
