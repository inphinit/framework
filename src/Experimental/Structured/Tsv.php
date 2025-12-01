<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Structured;

class Tsv extends Delimited
{
    protected $separators = array("\t");

    private static $breakLine = "\r\n";

    protected function getLine($separator)
    {
        $line = $this->chunk >= 1 ? fgets($this->stream, $this->chunk) : fgets($this->stream);

        if ($line === false || strpos($line, $separator) === false) {
            return false;
        }

        return explode($separator, trim($line, self::$breakLine));
    }
}
