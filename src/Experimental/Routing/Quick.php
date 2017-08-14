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
    private $ready = false;

    /**
     * Create two routes, one with slash at the end and other without, like: `/foo/` and `/foo`
     *
     * @var int
     */
    const BOTH = 1;

    /**
     * Create a route with slash at the end, like: `/foo/`
     *
     * @var int
     */
    const SLASH = 2;

    /**
     * Create a route without slash at the end, like: `/foo`
     *
     * @var int
     */
    const NOSLASH = 3;

    /**
     * Create routes based in a \Controller
     *
     * @param string $name Define Controller class name
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Routing\Quick
     */
    public static function create($name)
    {
        self::$debuglvl = 3;

        return new static($name);
    }

    /**
     * Create routes based in a \Controller
     *
     * @param string $name Define controllers class name
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($name)
    {
        $this->format = Quick::BOTH;

        $controller = strtr(parent::$prefixNS . $name, '.', '\\');

        $fc = '\\Controller\\' . $controller;

        if (class_exists($fc) === false) {
            throw new Exception('Invalid class ' . $fc, self::$debuglvl);
        }

        self::$debuglvl = 2;

        $this->classMethods = self::verbs(get_class_methods($fc));

        $this->fullController = $fc;
        $this->controller = $name;
    }

    /**
     * Extract valid methods
     *
     * @param array $methods Methods of \Controller class
     * @return array
     */
    private static function verbs(array $methods)
    {
        $list = array();
        $reMatch = '#^(any|get|post|patch|put|head|delete|options|trace|connect)([A-Z0-9]\w+)$#';

        foreach ($methods as $value) {
            $verb = array();

            if (preg_match($reMatch, $value, $verb)) {
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
     * @param int $slash define path format, choose `Quick::BOTH`, `Quick::SLASH` or `Quick::NOSLASH`
     * @return \Inphinit\Experimental\Routing\Quick
     */
    public function canonical($slash = Quick::NOSLASH)
    {
        switch ($slash) {
            case self::BOTH:
            case self::SLASH:
            case self::NOSLASH:
                $this->format = $slash;
                break;
            default:
                throw new Exception('Invalid type', 2);
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

        $format     = $this->format;
        $controller = $this->controller;

        foreach ($this->classMethods as $value) {
            if ($format === self::BOTH || $format === self::SLASH) {
                $route = '/' . (empty($value[1]) ? '' : ($value[1] . '/'));

                Route::set($value[0], $route, $controller . ':' . $value[2]);
            }

            if ($format === self::BOTH || $format === self::NOSLASH) {
                $route = empty($value[1]) ? '' : ('/' . $value[1]);

                Route::set($value[0], $route, $controller . ':' . $value[2]);
            }
        }

        $controller = $classMethods = null;
    }
}
