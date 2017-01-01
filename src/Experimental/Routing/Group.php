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
     * Create a new route group
     *
     * @return void
     */
    public function __construct()
    {
        App::on('init', array($this, 'prepare'));
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
        } elseif (!preg_match('~^(\/(.*?)\/|re\:#\^/(.*?)/#([imsxADSUXju]+))$~', $path)) {
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
            list($host, $port) = explode(':', Request::header('Host'), 2);

            if ($host === $this->domain) {
                return array();
            } elseif ($host && self::checkRegEx($this->domain, $host, $matches)) {
                array_shift($matches);
                return $matches;
            }
        }

        return false;
    }

    /**
     * Method is used for check path
     *
     * @return array|bool $matches
     */
    protected function checkPath()
    {
        if ($this->path) {
            $path = Request::path();

            if (strpos($path, $this->path) === 0) {
                $this->currentPrefixPath = $this->path;
                return array();
            } elseif (self::checkRegEx($this->path, $path, $matches)) {
                $this->currentPrefixPath = $matches[0];
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

        $args = array();

        $checks = 0;
        $valids = 0;

        if ($this->domain) {
            $checks++;
        }

        if ($this->path) {
            $checks++;
        }

        $argsDomain = $this->checkDomain();

        if ($argsDomain !== false) {
            $args = array_merge($args, $argsDomain);

            $valids++;
        }

        $argsPath = $this->checkPath();

        if ($argsPath !== false) {
            $args = array_merge($args, $argsPath);

            parent::$prefixPath = substr($this->currentPrefixPath, 0, -1);
            $valids++;
        }

        if ($valids === $checks) {
            parent::$prefixNS = $this->ns;
            call_user_func_array($this->callback, $args);
        }

        parent::$prefixNS = $oNS;
        parent::$prefixPath = $oPP;
    }
}
