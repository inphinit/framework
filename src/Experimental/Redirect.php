<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\App;
use Inphinit\Request;

class Redirect extends \Inphinit\Routing\Router
{
    private $preventFire = false;
    private $httpStatus = 302;

    /**
     * Create a Redirect instance
     *
     * @param string $path
     * @return void
     */
    public function __construct($path)
    {
        $this->httpPath = $path;
    }

    /**
     * Set HTTP status
     *
     * @param int $status
     * @return \Inphinit\Experimental\Redirect
     */
    public function status($status)
    {
        $this->httpStatus = $status;

        return $this;
    }

    /**
     * Redirect
     *
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function perform()
    {
        if (empty($this->httpPath)) {
            throw new Exception('Path is not defined', 2);
        }

        if (headers_sent()) {
            throw new Exception('Path is not defined', 2);
        }

        header('Location: ' . $this->httpPath, true, $this->httpStatus);

        if ($this->preventFire === false) {
            App::trigger('changestatus', array($status));
        }

        exit;
    }

    /**
     * Prevent trigger `changestatus` event
     *
     * @param bool $prevent
     * @return \Inphinit\Experimental\Redirect
     */
    public function prevent($prevent = true)
    {
        $this->preventFire = $prevent;

        return $this;
    }

    /**
     * Short-cut to redirect
     *
     * @param string $path
     * @param int    $status
     * @param bool   $prevent
     * @return void
     */
    public static function to($path, $status = 302, $prevent = false)
    {
        (new static($path))
            ->status($status)
            ->prevent($prevent)
            ->perform();
    }

    /**
     * Return to redirect to new path
     *
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Redirect
     */
    public static function back()
    {
        $referer = Resquest::header('referer');

        if ($referer === false) {
            return false;
        }

        return new static($referer);
    }

    /**
     * Return to a Route
     *
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Redirect
     */
    public static function route($path)
    {
        $path = '/' . ltrim($path, '/');
        $routes = array_filter(self::$httpRoutes);
        $valid = isset($routes['ANY ' . $path]) || isset($routes['GET ' . $path]);

        if ($valid === false && empty($routes) === false) {
            foreach ($routes as $route => $action) {
                if (self::find('GET', $route, $path, $args)) {
                    $valid = true;
                    break;
                }
            }
        }

        if ($valid === false) {
            throw new Exception('Invalid route', 2);
        }

        $req = urldecode(Request::path());
        $current = substr($req, 0, -strlen(Request::path(true)));

        return new static($current . $path);
    }
}
