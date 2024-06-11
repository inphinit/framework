<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

use Inphinit\Http\Request;
use Inphinit\Exception;

class Negotiation
{
    private $headers;

    /** Sort values in the header low to high by q-factors */
    const LOW = 1;

    /** Sort values in the header high to low by q-factors */
    const HIGH = 2;

    /** Get all values from a accept header (without q-factor) */
    const ALL = 3;

    /**
     * Create a Negotiation instance
     *
     * @param array $headers This parameter is optional, you can set with
     *                       headers returned by curl or other way
     * @return void
     */
    public function __construct(array $headers = null)
    {
        if (empty($headers) === false) {
            $headers = array_change_key_case($headers, CASE_LOWER);

            foreach ($headers as $key => $value) {
                if (
                    $key === 'accept-ranges' ||
                    strpos($key, 'accept-control-') === 0 ||
                    ($key !=='te' && $key !=='accept' && strpos($key, 'accept-') !== 0)
                ) {
                    unset($headers[$key]);
                }
            }

            $this->headers = $headers;

            $headers = null;
        }
    }

    public function __destruct()
    {
        $this->headers = null;
    }

    /**
     * Create a Negotiation instance based in string (eg.: `curl_opt(..., CURL_OPT_HEADER, true)`)
     *
     * @param string $str
     * @return void
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
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function acceptLanguage($sort = self::HIGH)
    {
        return $this->header('accept-language', $sort);
    }

    /**
     * Get all languages by `Accept-Charset` header and sort by q-factor (defined by `$sort`)
     *
     * @param int $sort Sorts charsets using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function acceptCharset($sort = self::HIGH)
    {
        return $this->header('accept-charset', $sort);
    }

    /**
     * Get all languages by `Accept-Encoding` header and sort by q-factor (defined by `$sort`)
     *
     * @param string $sort Sorts encodings using `LOW` or `HIGH` constants,
     *                      or return all in an simple array use `ALL` constant
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
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Exception
     * @return array|null
     */
    public function accept($sort = self::HIGH)
    {
        return $this->header('accept', $sort);
    }

    /**
     * Get the first language with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getLanguage($alternative = null)
    {
        $headers = $this->acceptLanguage();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Get the first charset with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getCharset($alternative = null)
    {
        $headers = $this->acceptCharset();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Get the first encoding with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function getEncoding($alternative = null)
    {
        $headers = $this->acceptEncoding();
        return $headers ? key($headers) : $alternative;
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
        $headers = $this->accept();
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
     * @param int    $sort
     * @throws \Inphinit\Exception
     * @return array
     */
    public static function qFactor($value, $sort = self::HIGH)
    {
        $headers = array();

        foreach (explode(',', $value) as $hvalues) {
            if (substr_count($hvalues, ';') > 1) {
                throw new Exception('Header contains a value with multiple semicolons: "' . $value . '"');
            }

            $current = explode(';', $hvalues, 2);

            if (isset($current[1])) {
                $qvalue = self::parseQValue($current[1]);
            } else {
                $qvalue = 1.0;
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
        $qvalue = str_replace('q=', '', $value);

        if (is_numeric($qvalue) === false) {
            throw new Exception('Header contains a q-factor non numeric: "' . $value . '"', 0, 3);
        } elseif ($qvalue > 1) {
            throw new Exception('Header contains a q-factor greater than 1 (value of q parameter can be from 0.0 to 1.0): "' . $value . '"', 0, 3);
        }

        return (float) $qvalue;
    }
}
