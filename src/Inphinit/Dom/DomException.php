<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
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
            $scheme = parse_url($file, PHP_URL_SCHEME);

            if (strcasecmp($scheme, 'file') === 0) {
                $file = preg_replace('#^file:/+#i', '', $file);
            }

            $this->file = $file;
            $this->line = $error->line;
            $trace = 0;
        }

        parent::__construct($error->message, $error->code, $trace);
    }
}
