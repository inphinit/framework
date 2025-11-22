<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Environment;

class EnvException extends \Inphinit\Exception
{
    public function __construct($message, $code, $filename, $line, $previous = null)
    {
        $this->file = $filename;
        $this->line = $line;

        parent::__construct(trim($message), $code, $previous);
    }
}
