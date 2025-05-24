<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Debugging\Inspector;

class Exception extends \Exception
{
    /**
     * Raise an exception
     *
     * @param string     $message
     * @param int        $code
     * @param int        $trace
     * @param \Throwable $previous
     */
    public function __construct($message, $code = 0, $trace = 2, $previous = null)
    {
        if ($trace > 0 && Inspector::caller($trace, $data)) {
            $this->file = $data['file'];
            $this->line = $data['line'];
        }

        parent::__construct(trim($message), $code, $previous);
    }
}
