<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Validation;

use Inphinit\Exception;

class FailureException extends Exception
{
    /**
     * Create a new exception instance, adjusting file and line
     * based on a specific stack trace depth.
     *
     * @param string $message
     * @param int    $trace
     */
    public function __construct($message, $trace)
    {
        parent::__construct($message, 0, $trace, null);
    }
}
