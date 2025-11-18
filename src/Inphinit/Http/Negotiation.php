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
     * Get all languages by `Accept-Language` header sorted by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts languages using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function acceptLanguage($sort = self::HIGH)
    {
        return $this->header('accept-language', $sort);
    }

    /**
     * Get all charsets by `Accept-Charset` header and sort by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts charsets using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function acceptCharset($sort = self::HIGH)
    {
        return $this->header('accept-charset', $sort);
    }

    /**
     * Get all encodings by  `Accept-Encoding` header and sort by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts encodings using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function acceptEncoding($sort = self::HIGH)
    {
        return $this->header('accept-encoding', $sort);
    }

    /**
     * Get all document types by `Accept` header and sorted by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts types using `LOW` or `HIGH` constants,
     *                  or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function accept($sort = self::HIGH)
    {
        return $this->header('accept', $sort);
    }

    /**
     * Get the first language with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getLanguage($alternative = null)
    {
        return $this->getFirst('acceptLanguage', $alternative);
    }

    /**
     * Get the first charset with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getCharset($alternative = null)
    {
        return $this->getFirst('acceptCharset', $alternative);
    }

    /**
     * Get the first encoding with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getEncoding($alternative = null)
    {
        return $this->getFirst('acceptEncoding', $alternative);
    }

    /**
     * Get the first "document type" with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getAccept($alternative = null)
    {
        return $this->getFirst('accept', $alternative);
    }

    private function getFirst($method, $alternative)
    {
        $getter = array($this, $method);
        $headers = $getter();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Parse any header with q-factor value
     *
     * @param string $header
     * @param int    $sort
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function header($header, $sort = self::HIGH)
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
            throw new Exception('Header contains a q-factor greater than 1 (value of q parameter can be from 0.0 to 1.0): "' . $value . '"', 0, 3);
        }

        return (float) $value;
    }
}
