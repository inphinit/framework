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
use Inphinit\Regex;
use Inphinit\Request;
use Inphinit\Routing\Router;
use Inphinit\Experimental\Exception;

class Group extends Router
{
    private $ready = false;
    private $currentPrefixPath;
    private $callback;
    private $domain;
    private $path;
    private $ns;
    private $arguments = array();
    private static $cachehost;

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
     * @return \Inphinit\Experimental\Routing\Group
     */
    public function prefixNS($namespace)
    {
        $this->ns = trim($namespace, '.\\') . '.';
        return $this;
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
        } elseif ($path !== '/' . trim($path, '/') . '/') {
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
     * @return void
     */
    public function then(\Closure $callback)
    {
        if ($this->ready) {
            return false;
        }

        $this->ready = true;

        if ($this->checkDomain() === false || $this->checkPath() === false) {
            return null;
        }

        $oNS = parent::$prefixNS;
        $oPP = parent::$prefixPath;

        if ($this->ns) {
            parent::$prefixNS = $this->ns;
        }

        if ($this->path) {
            parent::$prefixPath = rtrim($this->currentPrefixPath, '/');
        }

        call_user_func_array($callback, $this->arguments);

        parent::$prefixNS = $oNS;
        parent::$prefixPath = $oPP;
    }

    /**
     * Method is used for check domain and return arguments if using regex
     *
     * @return bool
     */
    protected function checkDomain()
    {
        if ($this->domain === null) {
            return true;
        }

        if (self::$cachehost !== null) {
            $host = self::$cachehost;
        } else {
            $fhost = Request::header('Host');
            $host = strstr($fhost, ':', true);
            $host = $host ? $host : $fhost;

            self::$cachehost = $host;
        }

        if ($host === $this->domain) {
            return true;
        } elseif ($host) {
            $re = Regex::parse($this->domain);

            if ($re === false || preg_match('#^' . $re . '$#', $host, $matches) === 0) {
                return false;
            }

            array_shift($matches);

            $this->arguments = array_merge($this->arguments, $matches);

            return true;
        }

        return false;
    }

    /**
     * Method is used for check path
     *
     * @return bool
     */
    protected function checkPath()
    {
        if ($this->path === null) {
            return true;
        }

        $pathinfo = \UtilsPath();

        if (strpos($pathinfo, $this->path) === 0) {
            $this->currentPrefixPath = $this->path;
            return true;
        } else {
            $re = Regex::parse($this->path);

            if ($re !== false && preg_match('#^' . $re . '#', $pathinfo, $matches)) {
                $this->currentPrefixPath = $matches[0];

                array_shift($matches);

                $this->arguments = array_merge($this->arguments, $matches);

                return true;
            }
        }

        return false;
    }
}
