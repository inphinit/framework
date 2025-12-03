<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Delimited;

class Tsv extends Reader
{
    protected $separators = array("\t");

    /**
     * Open file with tab-separated values
     *
     * @param string $path Set TSV file path
     * @throws \Inphinit\Exception
     */
    public function __construct($path)
    {
        parent::__construct($path);
    }

    /**
     * Parse header and lines
     *
     * @param string $separator Field separator
     * @param string $entry     Line content
     * @return array<int, string>|false
     */
    protected function parse($separator, $entry)
    {
        return explode($separator, $entry);
    }
}
