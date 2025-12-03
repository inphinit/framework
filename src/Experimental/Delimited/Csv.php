<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Delimited;

use Inphinit\Exception;

class Csv extends Reader
{
    protected $separators = array(',', ';', "\t", '|', ':', '~');

    private $enclosure = '"';
    private $proprietaryEscape;

    /**
     * Open file with comma-separated values
     *
     * @param string $path Set CSV file path
     * @throws \Inphinit\Exception
     */
    public function __construct($path)
    {
        // Note: Prior to PHP 7.4, there was no way to disable the proprietary escape mechanism
        $this->proprietaryEscape = PHP_VERSION_ID < 70400 ? '\\' : '';

        parent::__construct($path);
    }

    /**
     * Set enclosure for read CSV
     *
     * @param string $enclosure
     * @throws \Inphinit\Exception
     */
    public function setEnclosure($enclosure)
    {
        $this->isSingleChar($enclosure, true, 'Enclosure must be a single byte character or empty');
        $this->enclosure = $enclosure;
    }

    /**
     * Set proprietary escape mechanism for read CSV
     *
     * @param string $escape
     * @throws \Inphinit\Exception
     */
    public function setProprietaryEscape($escape)
    {
        $this->isSingleChar($escape, true, 'Proprietary escape must be a single byte character or empty');
        $this->proprietaryEscape = $escape;
    }

    /**
     * Set separators
     *
     * @param array<int, string> $separators
     * @throws \Inphinit\Exception
     */
    public function setSeparators(array $separators)
    {
        if (count($separators) === 0) {
            throw new Exception('Invalid separators');
        }

        $this->separator = null;
        $this->separators = $separators;
    }

    /**
     * Parse lines
     *
     * @param string $separator Field separator
     * @param string $entry     Line content
     * @return array<int, string>|false
     */
    protected function parse($separator, $entry)
    {
        $parsed = str_getcsv($entry, $separator, $this->enclosure, $this->proprietaryEscape);

        // Caution: On an empty string this function returns the value `[null]` instead of an empty array
        return is_array($parsed) === false || $parsed[0] === null ? array() : $parsed;
    }

    private static function isSingleChar($str, $allowEmpty, $message)
    {
        if (is_string($str) === false || strlen($str) > 1 || ($allowEmpty === false && strlen($str) !== 1)) {
            throw new Exception($message, 0, 3);
        }
    }
}
