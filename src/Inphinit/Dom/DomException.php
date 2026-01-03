<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
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

        $file = $error->file;

        if ($file && $error->line > 0) {
            // Removing the file URI scheme for compatibility with `realpath(...)` function
            $file = preg_replace('#^file:/*#i', '/', $file);

            // Remove leading slash in absolute paths in Windows (e.g., `/D:/path/sample.txt` -> `D:/path/sample.txt`)
            $file = preg_replace('#^/([a-z]\:)#i', '$1', $file);

            $this->file = $file;
            $this->line = $error->line;

            $trace = 0;
        }

        parent::__construct($error->message, $error->code, $trace);
    }
}
