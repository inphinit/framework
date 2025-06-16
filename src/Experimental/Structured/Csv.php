<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Structured;

use Inphinit\Exception;

class Csv extends Delimited
{
    protected $separators = array(',', ';', "\t", '|', ':', '~');

    /**
     * Set enclosure for read CSV
     *
     * @param string $enclosure
     * @param bool $refresh
     */
    public function setEnclosure($enclosure, $refresh = true)
    {
        if (empty($enclosure) || is_string($enclosure) === false) {
            throw new Exception('Invalid enclosure');
        }

        if ($refresh && $this->enclosure !== $enclosure) {
            $this->boot();
        }

        $this->enclosure = $enclosure;
    }

    /**
     * Set escape for read CSV
     *
     * @param string $escape
     * @param bool $refresh
     */
    public function setEscape($escape, $refresh = true)
    {
        if (empty($escape) || is_string($escape) === false) {
            throw new Exception('Invalid escape');
        }

        if ($refresh && $this->escape !== $escape) {
            $this->boot();
        }

        $this->escape = $escape;
    }

    /**
     * Set separator for read CSV
     *
     * @param string $separator
     * @param bool $refresh
     */
    public function setSeparator($separator, $refresh = true)
    {
        if (empty($separator) || is_string($separator) === false) {
            throw new Exception('Invalid separator');
        }

        if ($refresh && $this->separator !== $separator) {
            $this->boot();
        }

        $this->separator = $separator;
    }
}

