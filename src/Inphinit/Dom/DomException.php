<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
     */
    public function __construct($message = null, $trace = 1)
    {
        $err = \libxml_get_errors();

        ++$trace;

        if ($message === null && isset($err[0]->message)) {
            $message = trim($err[0]->message);
        }

        if (isset($err[0]->file) && $err[0]->file !== '' && $err[0]->line > 0) {
            $file = $err[0]->file;

            $scheme = parse_url($file, PHP_URL_SCHEME);

            if (!$scheme || stripos($scheme, 'file') === 0) {
                $file = parse_url($file, PHP_URL_PATH);
                $file = preg_replace('#^\/([A-Z]\:)#i', '$1', $file);
            }

            if ($file) {
                $this->file = $file;
                $this->line = $err[0]->line;
                $this->code = $err[0]->code;
                $trace = 0;
            }
        }

        parent::__construct($message, 0, $trace);
    }
}
