<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Exception;
use Inphinit\Http\Request;

class Forwarded
{
    const PARAM_BY    = 'by';
    const PARAM_FOR   = 'for';
    const PARAM_HOST  = 'host';
    const PARAM_PROTO = 'proto';

    private $data = array();
    private $done = false;
    private $fallback;
    private $limit;
    private $source;

    private $alloweds;

    private static $delimiters = "()<>@,;:\\\"/[]?={} \t";

    /**
     * Constructor.
     *
     * @param string|null $header   Optional. raw "Forwarded" header value
     * @param int         $limit    Optional. Limit forwarded blocks
     * @param bool        $fallback Optional. Whether to use "X-Forwarded-*" headers as fallback
     */
    public function __construct($header = null, $limit = 100, $fallback = true)
    {
        $this->alloweds = array(
            self::PARAM_BY,
            self::PARAM_FOR,
            self::PARAM_HOST,
            self::PARAM_PROTO
        );

        $this->source = $header === null ? Request::header('forwarded') : $header;
        $this->limit = $limit;
        $this->fallback = $fallback;
    }

    /**
     * Returns a value from a specific Forwarded field.
     *
     * @param string $type         One of the PARAM_* constants: by, for, host, proto.
     * @param int    $index        Optional index. Defaults to last (-1).
     * @param mixed  $alternative  Value returned if not found.
     * @throws \Inphinit\Exception If the type is invalid or the index is out of range.
     * @return string|null
     */
    public function getParam($type, $index = -1, $alternative = null)
    {
        $this->parseSource();

        if (!in_array($type, $this->alloweds)) {
            throw new Exception("Invalid type: {$type}");
        }

        if ($index === -1) {
            $index = count($this->data) - 1;
        }

        if (isset($this->data[$index][$type])) {
            return $this->data[$index][$type];
        }

        return $alternative;
    }

    /**
     * Returns all parsed Forwarded entries as an array.
     *
     * @return array<int, array<string, string>>
     */
    public function getAll()
    {
        $this->parseSource();

        return $this->data;
    }

    private function parseSource()
    {
        if ($this->done) return;

        $source = $this->source;

        if (!$source && $this->fallback) {
            return $this->parseFallback();
        }

        $data = array();
        $forwarded = array();
        $length = strlen($source);

        $in_quotes = false;
        $escaping = false;
        $must_unescape = false;
        $parameter = null;

        $start = -1;
        $end = -1;

        $found = 0;
        $limit = $this->limit;
        $tab_char = "\t";

        for ($i = 0; $i < $length; ++$i) {
            if ($found > $limit) {
                throw new Exception('Excessive number of Forwarded blocks', 0, 3);
            }

            $char = $source[$i];

            if ($parameter === null) {
                if ($start === -1 && ($char === ' ' || $char === $tab_char)) continue;

                if (self::isTokenChar($char)) {
                    if ($start === -1) $start = $i;
                } elseif ($char === '=') {
                    if ($start === -1) self::unexpectedCharacter($source, $i);

                    $parameter = strtolower(substr($source, $start, $i - $start));
                    $start = -1;
                } else {
                    self::unexpectedCharacter($source, $i);
                }
            } elseif ($escaping) {
                if ($char === $tab_char || ctype_print($char) || self::isExtended($char)) {
                    $escaping = false;
                } else {
                    self::unexpectedCharacter($source, $i);
                }
            } elseif ($in_quotes) {
                if ($char === '\\') {
                    $escaping = true;
                    $must_unescape = true;

                    if ($start === -1) $start = $i;
                } elseif ($char === '"') {
                    $in_quotes = false;
                    $end = $i;
                } elseif ($start === -1) {
                    $start = $i;
                }
            } elseif (self::isTokenChar($char)) {
                if ($end !== -1) self::unexpectedCharacter($source, $i);

                if ($start === -1) $start = $i;
            } elseif ($char === '"' && $i > 0 && $source[$i - 1] === '=') {
                $in_quotes = true;
            } elseif (self::isDelimiter($char) || self::isExtended($char)) {
                if (($char === ',' || $char === ';') && ($start !== -1 || $end !== -1)) {
                    self::completeParameter($source, $must_unescape, $i, $start, $end, $forwarded[$parameter]);

                    if ($char === ',') {
                        ++$found;
                        $data[] = $forwarded;
                        $forwarded = array();
                    }

                    $must_unescape = false;
                    $parameter = null;
                    $start = $end = -1;
                } elseif ($in_quotes) {
                    if ($start === -1) $start = $i;
                } else {
                    self::unexpectedCharacter($source, $i);
                }
            } elseif ($char === ' ' || $char === $tab_char) {
                if ($end !== -1) continue;

                if ($start === -1) self::unexpectedCharacter($source, $i);

                $end = $i;
            } else {
                self::unexpectedCharacter($source, $i);
            }
        }

        // check if the end failed
        if ($parameter === null || $in_quotes) {
            throw new Exception('Unexpected end of input: ' . $source, 0, 3);
        }

        self::completeParameter($source, $must_unescape, $length, $start, $end, $forwarded[$parameter]);

        $data[] = $forwarded;

        $this->data = $data;
        $this->done = true;
    }

    private function parseFallback()
    {
        $entries = array();

        if ($for_header = Request::header('x-forwarded-for')) {
            $for_entries = explode(',', $for_header);

            if (count($for_entries) > $this->limit) {
                throw new Exception('Excessive number of Forwarded blocks', 0, 3);
            }

            foreach ($for_entries as &$entry) {
                $entries[] = array(self::PARAM_FOR => $entry);
            }
        }

        if (empty($entries)) $entries[] = array();

        if ($host_header = Request::header('x-forwarded-host')) {
            $entries[0][self::PARAM_HOST] = trim($host_header);
        }

        if ($proto_header = Request::header('x-forwarded-proto')) {
            $entries[0][self::PARAM_PROTO] = trim($proto_header);
        }

        if (empty($entry) === false) {
            $this->data = $entry;
        }

        $this->done = true;
    }

    private static function completeParameter($header, $mustUnescape, $index, $start, &$end, &$targetValue)
    {
        if ($start !== -1) {
            if ($end === -1) $end = $index;

            $value = substr($header, $start, $end - $start);

            if ($mustUnescape) $value = preg_replace('#\\\\(.)#', '$1', $value);

            $targetValue = $value;
        } else {
            $targetValue = '';
        }
    }

    private static function isDelimiter($char)
    {
        return strpos(self::$delimiters, $char) !== false;
    }

    private static function isTokenChar($char)
    {
        $code = ord($char);
        return $code > 31 && $code !== 127 && !self::isDelimiter($char);
    }

    private static function isExtended($char)
    {
        return ord($char) > 127;
    }

    private static function unexpectedCharacter($header, $pos)
    {
        throw new Exception(sprintf('Unexpected character `%s` at index %d', $header[$pos], $pos), 0, 4);
    }
}
