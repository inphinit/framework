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
     * @param bool $trigger Define `true` to prevent trigger `status` event
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function perform($trigger = true)
    {
        if (empty($this->httpPath)) {
            throw new Exception('Path is not defined', 2);
        }

        if (headers_sent()) {
            throw new Exception('Headers already sent', 2);
        }

        header('Location: ' . $this->httpPath, true, $this->httpStatus);

        if ($trigger) {
            App::trigger('changestatus', array($status));
        }

        exit;
    }

    /**
     * Short-cut to redirect
     *
     * @param string $path
     * @param int    $status
     * @param bool   $trigger
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public static function to($path, $status = 302, $trigger = true)
    {
        $redirect = new static($path);
        $redirect->status($status)->perform($trigger);
    }

    /**
     * Return to redirect to new path
     *
     * @param bool $trigger
     * @return \Inphinit\Experimental\Redirect
     */
    public static function back($trigger = true)
    {
        $referer = Request::header('referer');

        if ($referer === false) {
            return false;
        }

        static::to($referer, 302, $trigger);
    }
}
