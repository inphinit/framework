<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Exception extends \ErrorException
{
    /**
     * Raise an exception
     *
     * @param string $message
     * @param int    $trace
     * @param int    $code
     * @param int    $severity
     * @return void
     */
    public function __construct($message, $trace = 1, $code = 0, $severity = E_ERROR)
    {
        if ($trace < 1) {
            $trace = 1;
        }

        $data = Debug::caller($trace);
        parent::__construct($message, $code, $severity, $data['file'], $data['line']);
    }
}
