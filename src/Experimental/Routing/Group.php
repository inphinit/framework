<?php
/*
 * Inphinit
 *
 * Copyright (c) 2022 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Routing;

use Inphinit\Regex;
use Inphinit\Http\Request;
use Inphinit\Experimental\Exception;

class Group extends \Inphinit\Routing\Router
{
    /** Access with HTTP and HTTPS (default) */
    const BOTH = 1;

    /** Access only in HTTPS */
    const SECURE = 2;

    /** Access only without HTTPS */
    const NONSECURE = 3;

    private $levelSecure;
    private $ready = false;
    private $currentPrefixPath;
    private $domain;
    private $path;
    private $ns;
    private $arguments = array();
    private static $cacheHost;

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
        if (isset($domain[0]) === false || trim($domain) !== $domain) {
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
        $path = trim($path, '/');

        if ($path) {
            $path = '/' . $path . '/';
        }

        $this->path = $path;

        return $this;
    }

    /**
     * Access only with HTTPS, or only HTTP, or both
     *
     * @param int $level
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Routing\Group
     */
    public function secure($level)
    {
        if ($level < 1 || $level > 3) {
            throw new Exception('Invalid security level', 2);
        }

        $this->levelSecure = (int) $level;

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
    }

    /**
     * Method is used for check if HTTPS or HTTP or both
     *
     * @return bool
     */
    protected function checkSecurity()
    {
        if (!$this->levelSecure || $this->levelSecure === self::BOTH) {
            return true;
        }

        $secure = Request::is('secure');

        return ($this->levelSecure === self::SECURE && $secure) ||
               ($this->levelSecure === self::NONSECURE && !$secure);
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

        if (self::$cacheHost !== null) {
            $host = self::$cacheHost;
        } else {
            $host = Request::header('Host');

            self::$cacheHost = $host ? strtok($host, ':') : '';
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
