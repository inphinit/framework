<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
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

    private $data = [];
    private $done = false;
    private $source;

    private $alloweds;

    /**
     * Constructor.
     *
     * @param string|null $header   Optional raw "Forwarded" header value.
     * @param bool        $fallback Whether to use "X-Forwarded-*" headers as fallback.
     */
    public function __construct($header = null, $fallback = true)
    {
        $this->alloweds = [
            self::PARAM_BY,
            self::PARAM_FOR,
            self::PARAM_HOST,
            self::PARAM_PROTO
        ];

        if ($header) {
            $this->source = $header;
        } else {
            $source = Request::header('forwarded');

            if ($fallback) {
                $this->getFromFallback();
            } else {
                $this->source = $source;
            }
        }
    }

    /**
     * Returns a value from a specific Forwarded field.
     *
     * @param string $type        One of the PARAM_* constants: by, for, host, proto.
     * @param int    $index       Optional index. Defaults to last (-1).
     * @param mixed  $alternative Value returned if not found.
     *
     * @return string|null
     * @throws Exception If the type is invalid or the index is out of range.
     */
    public function getParam($type, $index = -1, $alternative = null)
    {
        $this->parseHeaderValue();

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
        $this->parseHeaderValue();
        return $this->data;
    }

    private function parseHeaderValue()
    {
        if (!$this->done && $this->source) {
            $blocks = explode(',', $this->source);

            if (count($blocks) > 100) {
                throw new Exception('Excessive number of Forwarded blocks', 0, 3);
            }

            foreach ($blocks as $index => $block) {
                $total = preg_match_all('#(\w+)=(".*?"|\[.*?\]|[^";\s]+)#', trim($block), $matches);

                if ($total > 4) {
                    throw new Exception("Unexpected format in the group {$index}: {$block}", 0, 3);
                }

                $keys = array_map('strtolower', $matches[1]);

                if ($total > count(array_unique($keys))) {
                    throw new Exception("There are duplicate keys in the group {$index}: {$block}", 0, 3);
                }

                if ($invalids = array_diff($keys, $this->alloweds)) {
                    throw new Exception("Invalid keys in group {$index}: " . implode(', ', $invalids), 0, 3);
                }

                $values = $matches[2];

                foreach ($values as &$value) {
                    $value = trim($value, '"[]');
                }

                $this->data[] = array_combine($keys, $values);
            }

            $this->done = true;
        }
    }

    private function getFromFallback()
    {
        $this->data[] = [
            self::PARAM_FOR   => Request::header('x-forwarded-for'),
            self::PARAM_HOST  => Request::header('x-forwarded-host'),
            self::PARAM_PROTO => Request::header('x-forwarded-proto'),
        ];

        $this->done = true;
    }
}
