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
     * Define namespace prefix to Controllers
     *
     * @var string
     */
    protected static $prefixNS = '';

    /**
     * Define namespace path to routes
     *
     * @var string
     */
    protected static $prefixPath = '';
}
