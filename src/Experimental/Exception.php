<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Exception extends \Exception
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
    public function __construct($message = null, $trace = 0, $code = 0)
    {
        if ($trace > 0) {
            $data = Debug::caller($trace);

            $this->file = $data['file'];
            $this->line = $data['line'];
        }

        parent::__construct($message, $code);
    }
}
