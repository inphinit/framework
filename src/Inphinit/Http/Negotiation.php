<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\Exception;

class Negotiation
{
    private $headers;

    /** @var int Sort values in the header low to high by q-factors */
    const LOW = 1;

    /** @var int Sort values in the header high to low by q-factors */
    const HIGH = 2;

    /** @var int Get all values from a accept header (without q-factor) */
    const ALL = 3;

    /**
     * Create a Negotiation instance from request headers or from a Array
     *
     * @param array $headers Optional. You can set with headers returned by cURL or other way
     */
    public function __construct(array $headers = array())
    {
        if (empty($headers) === false) {
            $this->headers = array_change_key_case($headers, CASE_LOWER);
        }
    }

    /**
     * Create a Negotiation instance based in string
     *
     * @param string $str
     * @return \Inphinit\Http\Negotiation
     */
    public static function fromString($str)
    {
        $headers = array();

        foreach (preg_split('#(\r)?\n#', $str) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', trim($line), 2);
                $headers[$key] = ltrim($value);
            }
        }

        $instance = new static($headers);

        $headers = null;

        return $instance;
    }

    /**
     * Parse any header with q-factor value
     *
     * @param string $header
     * @param int    $sort
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function entries($header, $sort = self::HIGH)
    {
        $header = strtolower($header);

        if ($header === 'accept-ranges' || strpos($header, 'accept-control-') === 0) {
            return null;
        }

        if ($this->headers) {
            $value = isset($this->headers[$header]) ? $this->headers[$header] : null;
        } else {
            $value = Request::header($header);
        }

        return $value ? self::qFactor($value, $sort) : null;
    }

    /**
     * Get all document types from `Accept` header and sorted by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts types using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function contentTypes($sort = self::HIGH)
    {
        return $this->entries('accept', $sort);
    }

    /**
     * Get all encodings from `Accept-Encoding` header and sort by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts encodings using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function encodings($sort = self::HIGH)
    {
        return $this->entries('accept-encoding', $sort);
    }

    /**
     * Get all languages from `Accept-Language` header sorted by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts languages using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function languages($sort = self::HIGH)
    {
        return $this->entries('accept-language', $sort);
    }

    /**
     * Get the highest priority option from the header
     *
     * @param mixed $header
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function top($header, $alternative)
    {
        $values = $this->entries($header, self::HIGH);
        return $values ? key($values) : $alternative;
    }

    /**
     * Get the document type (from `Accept` header) with the highest
     * priority based on the q value. If it does not exist then return
     * the value of `$alternative`.
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function topContentType($alternative = null)
    {
        return $this->top('accept', $alternative);
    }

    /**
     * Get the encoding (from `Accept-Encoding` header) with the highest
     * priority based on the q value. If it does not exist then return
     * the value of `$alternative`.
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function topEncoding($alternative = null)
    {
        return $this->top('accept-encoding', $alternative);
    }

    /**
     * Get the encoding (from `Accept-Language` header) with the highest
     * priority based on the q value. If it does not exist then return
     * the value of `$alternative`.
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function topLanguage($alternative = null)
    {
        return $this->top('accept-language', $alternative);
    }

    /**
     * Parse and sort a custom value with q-factor
     *
     * @param string $value
     * @param Negotiation::LOW|Negotiation::HIGH|Negotiation::ALL $sort
     * @throws \Inphinit\Exception
     * @return array
     */
    public static function qFactor($value, $sort = self::HIGH)
    {
        $headers = array();

        foreach (explode(',', $value) as $hvalues) {
            $current = explode(';', $hvalues, 2);

            if (strlen($current[0]) === 0) {
                continue;
            }

            $qvalue = 1.0;

            if (isset($current[1])) {
                $found = preg_match_all('#(^|;)\s*q\s*=\s*([^;]*)#', $current[1], $matches);

                if ($found > 1) {
                    throw new Exception('The header contains more than one q= in "' . $current[0] . '"');
                } elseif ($found === 1) {
                    $qvalue = self::parseQValue($matches[2][0]);
                }
            }

            $headers[trim($current[0])] = $qvalue;
        }

        if ($sort === self::ALL) {
            return array_keys($headers);
        }

        if ($sort === self::LOW) {
            asort($headers, SORT_NUMERIC);
        } else {
            arsort($headers, SORT_NUMERIC);
        }

        return $headers;
    }

    private static function parseQValue($value)
    {
        $value = trim($value);

        if (is_numeric($value) === false) {
            throw new Exception('Header contains a q-factor non numeric: "' . $value . '"', 0, 3);
        } elseif ($value > 1) {
            throw new Exception('Header contains a q-factor outside the range of 0.0–1.0: "' . $value . '"', 0, 3);
        }

        return (float) $value;
    }
}
