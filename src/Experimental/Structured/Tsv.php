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
        if (feof($this->stream)) {
            return false;
        }

        $line = fgets($this->stream, $this->chunk);

        if ($line === false || strpos($line, $separator) === false) {
            return false;
        }

        return explode($separator, rtrim($line, self::$breakLine));
    }
}
