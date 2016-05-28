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
use Inphinit\Request;
use Inphinit\Routing\Router;

/*
    Usage examples:

    use Experimental\Routing\Group;

    //Group by path, navigate to http://[server]/foo/bar
    Group::create()->path('/foo/')->call(function () {
        Route::set('GET', '/bar', 'Controller:action');
    });

    //Group by path with regexp, navite http://[server]/something/baz
    Group::create()->path('re:#^/([a-z]+)/#ui')->call(function ($arg1) {
        Route::set('GET', '/baz', 'Controller:action');
    });

    //Group by subdomain, navite http://[server]/something/baz
    Group::create()->domain('re:#^([a-z]+)\.server\.io$#i')->call(function ($arg1) {
        Route::set('GET', '/baz', 'Controller:action');
    });


    //Combined group subdomain+path
    Group::create()
        ->domain('re:#^([a-z]+)\.server\.io$#i')
        ->path('re:#/([a-z]+)/$#i')
        ->call(function ($userDomain, $path) {
            Route::set('GET', '/', 'Controller:action');
        });
*/

class Group extends Router
{
    private $ready = false;
    private $domain;
    private $path;
    private $ns;

    public static function create()
    {
        return new static;
    }

    public function __construct()
    {
        App::on('init', array($this, 'prepare'));
    }


    public function prefixNS($namespace)
    {
        if (preg_match('#\\[a-z0-9_\\]+[a-z0-9_]$#', $namespace) === 0) {
            Exception::raise('Invalid "' . $namespace . '"', 2);
        }

        $this->ns = $namespace;
    }

    public function domain($domain)
    {
        if (empty($domain)) {
            Exception::raise('domain is not defined', 2);
        }

        if (empty($domain) || trim($domain) !== $domain) {
            Exception::raise('Invalid domain "' . $domain . '"', 2);
        } else {
            $this->domain = $domain;
        }

        return $this;
    }

    public function path($path)
    {
        if (empty($path)) {
            Exception::raise('path is not defined', 2);
        } elseif (!preg_match('~^(\/(.*?)\/|re\:#\^/(.*?)/#([imsxADSUXju]+))$~', $path)) {
            Exception::raise('missing slash in "' . $path . '", use like this /foo/', 2);
        }

        $this->path = $path;

        return $this;
    }

    public function call(\Closure $call)
    {
        $this->call = $call;

        return $this;
    }

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

            parent::$prefixPath += substr($this->currentPrefixPath, 0, -1);
            $valids++;
        }

        if ($valids === $checks) {
            parent::$prefixNS += $this->ns;
            call_user_func_array($this->call, $args);
        }

        parent::$prefixNS = $oNS;
        parent::$prefixPath = $oPP;
    }
}
