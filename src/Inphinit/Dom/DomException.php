<?php
/*
 * Inphinit
 *
 * Copyright (c) 2021 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Dom;

class DomException extends \Inphinit\Exception
{
    /**
     * Raise an exception
     *
     * @param string $message
     * @param int    $trace
     * @return void
     */
    public function __construct($message = null, $trace = 1)
    {
        $err = \libxml_get_errors();

        ++$trace;

        if ($message === null && isset($err[0]->message)) {
            $message = trim($err[0]->message);
        }

        if (isset($err[0]->file) && $err[0]->line > 0) {
            $this->file = preg_replace('#^file:/(\w+:)#i', '$1', $err[0]->file);
            $this->line = $err[0]->line;
            $this->code = $err[0]->code;
            $trace = 0;
        }

        parent::__construct($message, $trace);
    }
}
