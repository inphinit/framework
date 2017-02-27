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

class Quick extends Router
{
    private static $debuglvl = 2;
    private $classMethods = array();
    private $fullController;
    private $controller;
    private $format;
    private $prefix;
    private $ready = false;

    /** Create two routes, one with slash at the end and other without, like: `/foo/` and `/foo` */
    const BOTH = 1;

    /** Create a route with slash at the end, like: `/foo/` */
    const SLASH = 2;

    /** Create a route without slash at the end, like: `/foo` */
    const NOSLASH = 3;

    /**
     * Create routes based in a \Controller
     *
     * @param string Define prefix controller namespace
     * @param string Define Controller class name
     * @return \Inphinit\Experimental\Routing\Quick
     */
    public static function create($namecontroller, $prefix = '')
    {
        self::$debuglvl = 3;

        return new static($namecontroller, $prefix);
    }

    /**
     * Create routes based in a \Controller
     *
     * @param string Define prefix controller namespace
     * @param string Define controllers class name
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($namecontroller, $prefix = '')
    {
        $this->format = Quick::BOTH;

        $controller = parent::$prefixNS . strtr($namecontroller, '.', '\\');

        $fc = '\\Controller\\' . $controller;

        if (class_exists($fc) === false) {
            throw new Exception('Invalid class ' . $fc, self::$debuglvl);
        }

        self::$debuglvl = 2;

        $this->classMethods = self::parseVerbs(get_class_methods($fc));

        $this->controller = $namecontroller;
        $this->fullController = $fc;

        $this->prefix = empty($prefix) ? '' : ('/' . trim($prefix, '/'));

        App::on('init', array($this, 'prepare'));
    }

    /**
     * Extract valid methods
     *
     * @param string Methods of \Controller class
     * @return array
     */
    private static function parseVerbs($methods)
    {
        $list = array();
        $reMatch = '#^(any|get|post|patch|put|head|delete|options|trace|connect)([a-zA-Z0-9_]+)$#';

        foreach ($methods as $value) {
            $verb = array();

            if (preg_match($reMatch, $value, $verb) > 0) {
                if (strcasecmp('index', $verb[2]) === 0) {
                    $verb[2] = '';
                } else {
                    $verb[2] = strtolower(preg_replace('#([a-z])([A-Z])#', '$1-$2', $verb[2]));
                }

                $list[] = array(strtoupper($verb[1]), $verb[2], $value);
            }
        }

        return $list;
    }

    /**
     * Define route format, `Quick::BOTH` for create routes like `/foo/` and `/foo`, `Quick::SLASH`
     * for create routes like `/foo/` and `Quick::NOSLASH` for create routes like `/foo`
     *
     * @param int define path format, choose `Quick::BOTH`, `Quick::SLASH` or `Quick::NOSLASH`
     * @return \Inphinit\Experimental\Routing\Quick
     */
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

    /**
     * Create routes by configurations
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

        if (empty($this->classMethods)) {
            throw new Exception($this->fullController . ' is empty ', 2);
        }

        $format       = $this->format;
        $controller   = $this->controller;
        $classMethods = $this->classMethods;

        foreach ($classMethods as $value) {
            if ($format === self::BOTH || $format === self::SLASH) {
                $route = $this->prefix . '/' . (empty($value[1]) ? '' : ($value[1] . '/'));

                Route::set($value[0], $route, $controller . ':' . $value[2]);
            }

            if ($format === self::BOTH || $format === self::NOSLASH) {
                $route = $this->prefix . (empty($value[1]) ? '' : ('/' . $value[1]));

                Route::set($value[0], $route, $controller . ':' . $value[2]);
            }
        }

        $controller = $classMethods = null;
    }
}
