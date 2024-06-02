<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Debugging\Debug;

class Exception extends \Exception
{
    /**
     * Raise an exception
     *
     * @param string $message
     * @param int    $code
     * @param int    $trace
     * @return void
     */
    public function __construct($message, $code = 0, $trace = 0)
    {
        if ($trace > 0) {
            $data = Debug::caller($trace);

            $this->file = $data['file'];
            $this->line = $data['line'];
        }

        parent::__construct($message, $code);
    }
}
