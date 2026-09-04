<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

use Inphinit\Diagnostics\Inspector;

class Exception extends \Exception
{
    /**
     * Create a new exception instance, optionally adjusting file and line
     * based on a specific stack trace depth.
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

        parent::__construct($message, $code, $previous);
    }
}
