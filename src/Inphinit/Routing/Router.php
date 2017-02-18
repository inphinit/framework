<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Routing;

abstract class Router
{
    /**
     * Store all routes
     *
     * @var array
     */
    protected static $httpRoutes = array();

    /**
     * Define namespace prefix to Controllers
     *
     * @var string
     */
    protected static $prefixNS = '';

    /**
     * Define path prefix to routes
     *
     * @var string
     */
    protected static $prefixPath = '';
}
