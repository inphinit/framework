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
use Inphinit\Request;
use Inphinit\Routing\Router;
use Inphinit\Experimental\Exception;

class Group extends Router
{
    private $ready = false;
    private $callback;
    private $domain;
    private $path;
    private $ns;

    /**
     * Create a new route group
     *
     * @return \Inphinit\Experimental\Routing\Group
     */
    public static function create()
    {
        return new static;
    }

    /**
     * Define namespace prefix for group
     *
     * @param string $namespace
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function prefixNS($namespace)
    {
        if (preg_match('#\\[a-z0-9_\\]+[a-z0-9_]$#', $namespace) === 0) {
            throw new Exception('Invalid "' . $namespace . '"', 2);
        }

        $this->ns = $namespace;
    }

    /**
     * Define domain for group
     *
     * @param string $domain
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Routing\Group
     */
    public function domain($domain)
    {
        if (empty($domain)) {
            throw new Exception('domain is not defined', 2);
        }

        if (empty($domain) || trim($domain) !== $domain) {
            throw new Exception('Invalid domain "' . $domain . '"', 2);
        } else {
            $this->domain = $domain;
        }

        return $this;
    }

    /**
     * Define path for group
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Routing\Group
     */
    public function path($path)
    {
        if (empty($path)) {
            throw new Exception('path is not defined', 2);
        } elseif (preg_match('#^/(.*?)/$#', $path) === 0) {
            throw new Exception('missing slash in "' . $path . '", use like this /foo/', 2);
        }

        $this->path = $path;

        return $this;
    }

    /**
     * Define callback for group, this callback is executed if the request meets the group
     * settings
     *
     * @param \Closure
     * @return \Inphinit\Experimental\Routing\Group
     */
    public function then(\Closure $callback)
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Method is used for check domain and path
     *
     * @param string $str
     * @param string $input
     * @param array  $matches
     * @return bool
     */
    protected static function checkRegEx($str, $input, &$matches)
    {
        if (strpos($str, 're:') !== 0) {
            return false;
        }

        $re = explode('re:', $str, 2);

        $matches = array();

        if (preg_match($re[1], $input, $matches) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Method is used for check domain
     *
     * @return array|bool $matches
     */
    protected function checkDomain()
    {
        if ($this->domain) {
            $host = Request::header('Host');

            if ($host === $this->domain) {
                return array();
            } elseif ($host) {
                $re = parent::parse($this->domain);

                if ($re === false || preg_match_all('#^' . $re . '$#', $host, $matches) === 0) {
                    return false;
                }

                array_shift($matches);
                return $matches;
            }
        }

        return false;
    }

    /**
     * Perform checking and execute predefined callback
     *
     * @return void
     */
    public function prepare()
    {
        if ($this->ready) {
            return false;
        }

        $this->ready = true;

        $oNS = parent::$prefixNS;
        $oPP = parent::$prefixPath;

        $argsDomain = false;

        if ($this->domain) {
            $argsDomain = $this->checkDomain();
        }

        if ($this->path || $argsDomain !== false) {
            parent::$prefixNS = $this->ns;

            if ($this->path) {
                parent::$prefixPath = rtrim($this->path, '/');
            }

            call_user_func_array($this->callback, $argsDomain ? $argsDomain : array());
        }

        parent::$prefixNS = $oNS;
        parent::$prefixPath = $oPP;
    }
}
