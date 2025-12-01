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
     * @throws \Inphinit\Exception
     */
    public function setEnclosure($enclosure, $refresh = true)
    {
        $this->isValid('enclosure', $enclosure);
        $this->updateControl('enclosure', $enclosure, $refresh);
    }

    /**
     * Set proprietary escape mechanism for read CSV
     *
     * @param string $escape
     * @param bool $refresh
     * @throws \Inphinit\Exception
     */
    public function setEscape($escape, $refresh = true)
    {
        $this->isValid('escape', $escape);
        $this->updateControl('escape', $escape, $refresh);
    }

    /**
     * Set separator for read CSV
     *
     * @param string $separator
     * @param bool $refresh
     * @throws \Inphinit\Exception
     */
    public function setSeparator($separator, $refresh = true)
    {
        $this->isValid('separator', $separator);

        $newValue = array($separator);

        if ($this->separators !== $newValue) {
            $this->separators = $newValue;

            if ($refresh) {
                $this->boot();
            }
        }
    }
}
