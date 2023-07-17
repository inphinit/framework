<?php
/*
 * Inphinit
 *
 * Copyright (c) 2023 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Routing;

use Inphinit\Routing\Route;
use Inphinit\Experimental\Exception;

class Quick extends \Inphinit\Routing\Router
{
    private static $debuglvl = 2;
    private $classMethods = array();
    private $fullController;
    private $controller;
    private $format;
    private $ready = false;
    private $path = '/';

    /** Create two routes, one with slash at the end and other without, like: `/foo/` and `/foo`, is not valid to Index method */
    const BOTH = 1;

    /** Create a route with slash at the end, like: `/foo/` */
    const SLASH = 2;

    /** Create a route without slash at the end, like: `/foo` */
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

        $controller = '\\Controller\\' . strtr(parent::$prefixNS . $name, '.', '\\');

        if (class_exists($controller) === false) {
            $level = self::$debuglvl;

            self::$debuglvl = 2;

            throw new Exception('Invalid class ' . $controller, $level);
        }

        $cm = self::verbs($controller);

        if (empty($cm)) {
            $level = self::$debuglvl;

            self::$debuglvl = 2;

            throw new Exception($this->fullController . ' is empty', $level);
        }

        $this->classMethods = $cm;

        $cm = null;

        self::$debuglvl = 2;

        $this->fullController = $controller;
        $this->controller = $name;
    }

    private static function verbs($controller)
    {
        $list = array();
        $reMatch = '#^(any|get|post|patch|put|head|delete|options|trace|connect)([A-Z0-9]\w+)$#';

        foreach (get_class_methods($controller) as $value) {
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
     * @param int $slash Define path format, choose `Quick::BOTH`, `Quick::SLASH` or `Quick::NOSLASH`
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
     * Define prefix path for all routes in class
     *
     * @param string $prefix
     * @return \Inphinit\Experimental\Routing\Quick
     */
    public function path($path)
    {
        if ($path) {
            $this->path = '/' . trim(str_replace('//', '/', $path), '/') . '/';
        } else {
            $this->path = '/';
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
        if ($this->ready === false) {
            $this->ready = true;

            $path = $this->path;
            $format = $this->format;
            $controller = $this->controller;

            foreach ($this->classMethods as $value) {
                $route = null;

                if ($format === self::BOTH || $format === self::SLASH) {
                    $route = ($value[1] === '' ? '' : ($value[1] . '/'));
                }

                if ($format === self::BOTH || $format === self::NOSLASH) {
                    $route = $value[1] === '' ? '' : ('/' . $value[1]);
                }

                if ($route !== null) {
                    Route::set($value[0], $path . $route, $controller . ':' . $value[2]);
                }
            }
        }
    }
}
