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
        self::isValid($enclosure, 'enclosure', 3);
        $this->refreshBoot('enclosure', $enclosure, $refresh);
    }

    /**
     * Set escape for read CSV
     *
     * @param string $escape
     * @param bool $refresh
     */
    public function setEscape($escape, $refresh = true)
    {
        self::isValid($escape, 'escape', 3);
        $this->refreshBoot('escape', $escape, $refresh);
    }

    /**
     * Set separator for read CSV
     *
     * @param string $separator
     * @param bool $refresh
     */
    public function setSeparator($separator, $refresh = true)
    {
        self::isValid($separator, 'separator', 3);
        $this->refreshBoot('separator', $separator, $refresh);
    }
}

