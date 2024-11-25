<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

use Inphinit\Exception;
use Inphinit\Http\Request;
use Inphinit\Regex;

class Group extends Router
{
    private $ready = false;
    private $pathPrefix;
    private $namespacePrefix;

    private $domainRequired;
    private $pathRequired;
    private $secureRequired;

    private $arguments = array();
    private static $cacheHost;

    /**
     * Create a new route group
     *
     * @return \Inphinit\Routing\Group
     */
    public static function create()
    {
        return new static;
    }

    /**
     * Define namespace prefix for group
     *
     * @param string $namespace
     * @return \Inphinit\Routing\Group
     */
    public function prefixNS($namespace)
    {
        $this->namespacePrefix = trim($namespace, '.\\') . '.';
        return $this;
    }

    /**
     * Define domain for group
     *
     * @param string $domain
     * @throws \Inphinit\Exception
     * @return \Inphinit\Routing\Group
     */
    public function domain($domain)
    {
        if (isset($domain[0]) === false || trim($domain) !== $domain) {
            throw new Exception('Invalid domain "' . $domain . '"', 0, 2);
        } else {
            $this->domainRequired = $domain;
        }

        return $this;
    }

    /**
     * Define path for group
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return \Inphinit\Routing\Group
     */
    public function path($path)
    {
        $path = trim($path, '/');

        if ($path) {
            $path = '/' . $path . '/';
        }

        $this->pathRequired = $path;

        return $this;
    }

    /**
     * Access only with HTTPS or only HTTP
     *
     * @param bool $secure Define true for only accepet HTTPS, or define false for only HTTP
     * @throws \Inphinit\Exception
     * @return \Inphinit\Routing\Group
     */
    public function secure($secure)
    {
        $this->secureRequired = $secure;

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
        if ($this->ready === false && $this->checkDomain() && $this->checkPath() && $this->checkSecurity()) {
            $this->ready = true;

            $oNS = parent::$prefixNS;
            $oPP = parent::$prefixPath;

            if ($this->namespacePrefix) {
                parent::$prefixNS = $this->namespacePrefix;
            }

            if ($this->pathRequired) {
                parent::$prefixPath = rtrim($this->pathPrefix, '/');
            }

            call_user_func_array($callback, $this->arguments);

            parent::$prefixNS = $oNS;
            parent::$prefixPath = $oPP;
        }
    }

    /**
     * Method is used for check if HTTPS or HTTP or both
     *
     * @return bool
     */
    protected function checkSecurity()
    {
        if ($this->secureRequired === null) {
            return true;
        }

        $secure = Request::is('secure');

        if ($secure) {
            return $this->secureRequired === true;
        }

        return $this->secureRequired === false;
    }

    /**
     * Method is used for check domain and return arguments if using regex
     *
     * @return bool
     */
    protected function checkDomain()
    {
        $domainRequired = $this->domainRequired;

        if ($domainRequired === null) {
            return true;
        }

        if (self::$cacheHost !== null) {
            $host = self::$cacheHost;
        } else {
            $host = Request::header('Host');

            $host = self::$cacheHost = $host ? strtok($host, ':') : '';
        }

        if ($host === $domainRequired) {
            return true;
        } elseif ($host && strpos($domainRequired, '{:') !== false) {
            $re = Regex::parse($domainRequired);

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
        $pathRequired = $this->pathRequired;

        if ($pathRequired === null) {
            return true;
        }

        $pathinfo = INPHINIT_PATH;

        if (strpos($pathinfo, $pathRequired) === 0) {
            $this->pathPrefix = $pathRequired;
            return true;
        } elseif (strpos($pathRequired, '{:') !== false) {
            $re = Regex::parse($this->pathRequired);

            if ($re !== false && preg_match('#^' . $re . '#', $pathinfo, $matches)) {
                $this->pathPrefix = $matches[0];

                array_shift($matches);

                $this->arguments = array_merge($this->arguments, $matches);

                return true;
            }
        }

        return false;
    }
}
