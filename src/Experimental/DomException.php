<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class DomException extends \ErrorException
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
    public function __construct($message = null)
    {
        $err = \libxml_get_errors();

        if (isset($err[0], $err[0]->file) && $err[0]->file) {
            $this->message = empty($message) ? $err[0]->message : $message;
            $this->file = preg_replace('#^file:/([a-zA-Z]+:)#i', '$1', $err[0]->file);
            $this->line = $err[0]->line;
        }

        parent::__construct();
    }
}
