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
     * @param \LibXMLError $error
     * @param int          $trace
     */
    public function __construct(\LibXMLError $error, $trace = 1)
    {
        ++$trace;

        if ($error->file && $error->line > 0) {
            $file = $error->file;
            $scheme = parse_url($file, PHP_URL_SCHEME);

            if (!$scheme || stripos($scheme, 'file') === 0) {
                $file = parse_url($file, PHP_URL_PATH);
                $file = preg_replace('#^/+([A-Z]\:)#i', '$1', $file);
            }

            if ($file) {
                $this->file = $file;
                $this->line = $error->line;
                $trace = 0;
            }
        }

        parent::__construct($error->message, $error->code, $trace);
    }
}
