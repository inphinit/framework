<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Routing;

use Inphinit\App;
use Inphinit\Routing\Route;
use Inphinit\Routing\Router;

use Inphinit\Experimental\Exception;

/*
use Inphinit\Experimental\Routing\Quick;

Quick::create('Namespace.level2.classname');
*/

class Quick extends Router
{
    private $classMethods = array();
    private $fullController;
    private $controller;
    private $format;
    private $ready = false;

    const BOTH = 1;
    const SLASH = 2;
    const NOSLASH = 3;

    public static function create($namecontroller)
    {
        return new static($namecontroller);
    }

    public function __construct($namecontroller)
    {
        $this->format = Quick::BOTH;

        $controller = parent::$prefixNS . strtr($namecontroller, '.', '\\');
        $fc = '\\Controller\\' . $controller;

        if (class_exists($fc) === false) {
            Exception::raise('Invalid class ' . $fc, 2);
        }

        $this->classMethods = self::parseVerbs(get_class_methods($fc));

        $this->controller = $namecontroller;
        $this->fullController = $fc;

        App::on('init', array($this, 'prepare'));
    }

    private static function parseVerbs($methods)
    {
        $list = array();

        foreach ($methods as $value) {
            $verb = array();

            if (preg_match('#^(any|get|post|patch|put|head|delete|options|trace|connect)([a-zA-Z0-9_]+)$#', $value, $verb) > 0) {
                if (strcasecmp('index', $verb[2]) === 0) {
                    $verb[2] = '';
                } else {
                    //Next update: Convert camelCase to camel-case
                }

                $list[] = array(strtoupper($verb[1]), strtolower($verb[2]), $value);
            }
        }

        return $list;
    }

    public function canonical($slash = null)
    {
        switch ($slash) {
            case self::BOTH:
            case self::SLASH:
            case self::NOSLASH:
                $this->format = $slash;
            break;
        }

        return $this;
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

        $format       = $this->format;
        $controller   = $this->controller;
        $classMethods = $this->classMethods;

        foreach ($classMethods as $value) {
            if ($value[1] !== '' && ($format === self::BOTH || $format === self::SLASH)) {
                Route::set($value[0], '/' . $value[1] . '/', $controller . ':' . $value[2]);
            }

            if ($format === self::BOTH || $format === self::NOSLASH) {
                Route::set($value[0], '/' . $value[1], $controller . ':' . $value[2]);
            }
        }

        $controller = $classMethods = null;
    }
}
