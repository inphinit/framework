<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Http\Request;
use Inphinit\Experimental\Exception;

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
        $headers = array_change_key_case($headers ? $headers : Request::header(), CASE_LOWER);

        $this->headers = array_filter($headers, array($this, 'filter'), ARRAY_FILTER_USE_KEY);

        $headers = null;
    }

    /**
     * Get all languages by `Accept-Language` header sorted by q-factor (defined by `$level`)
     *
     * @param int $level Sorts languages using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function languages($level = self::HIGH)
    {
        return $this->header('accept-language', $level);
    }

    /**
     * Get all languages by `Accept-Charset` header and sort by q-factor (defined by `$level`)
     *
     * @param int $level Sorts charsets using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array
     */
    public function charsets($level = self::HIGH)
    {
        return $this->header('accept-charset', $level);
    }

    /**
     * Get all languages by `Accept-Encoding` header and sort by q-factor (defined by `$level`)
     *
     * @param string $level Sorts encodings using `LOW` or `HIGH` constants,
     *                      or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function encodings($level = self::HIGH)
    {
        return $this->header('accept-encoding', $level);
    }

    /**
     * Get all document types by `Accept` header and sorted by q-factor (defined by `$level`)
     *
     * @param int $level Sorts types using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function types($level = self::HIGH)
    {
        return $this->header('accept', $level);
    }

    /**
     * Get the first language with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getLanguage($alternative = false)
    {
        $header = $this->languages();
        return $header ? $header[0]['value'] : $alternative;
    }

    /**
     * Get the first charset with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getCharset($alternative = false)
    {
        $header = $this->charsets();
        return $header ? $header[0]['value'] : $alternative;
    }

    /**
     * Get the first encoding with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getEncoding($alternative = false)
    {
        $header = $this->encodings();
        return $header ? $header[0]['value'] : $alternative;
    }

    /**
     * Get the first language with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getType($alternative = false)
    {
        $header = $this->types();
        return $header ? $header[0]['value'] : $alternative;
    }

    /**
     * Parse any header like `TE` header or headers with `Accepet-` prefix
     *
     * @param string $header
     * @param int    $level
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function header($header, $level = self::HIGH)
    {
        $header = strtolower($header);

        if (empty($this->headers[$header])) {
            return false;
        }

        return self::qFactor($this->headers[$header], $level);
    }

    /**
     * Parse and sort a custom value with q-factor
     *
     * @param string $value
     * @param int    $level
     * @throws \Inphinit\Experimental\Exception
     * @return array
     */
    public static function qFactor($value, $level = self::HIGH)
    {
        $multivalues = explode(',', $value);

        foreach ($multivalues as &$hvalues) {
            if (substr_count($hvalues, ';') > 1) {
                throw new Exception('Header contains a value with multiple semicolons: "' . $value . '"', 2);
            }

            $current = explode(';', $hvalues, 2);

            if (empty($current[1])) {
               $qvalue = 1.0;
            } else {
                $qvalue = self::parseQValue($current[1]);
            }

            $hvalues = array(
                'value' => trim($current[0]),
                'qfactor' => $qvalue
            );
        }

        if ($level === self::ALL) {
            foreach ($multivalues as &$item) {
                $item = $item['value'];
            }

            return $multivalues;
        }

        usort($multivalues, function ($a, $b) use ($level) {
            if ($level === self::LOW) {
                return $a['qfactor'] > $b['qfactor'];
            } else {
                return $a['qfactor'] <= $b['qfactor'];
            }
        });

        return $multivalues;
    }

    private function filter($key)
    {
        return $key === 'te' || (
            $key !== 'accept-ranges' &&
            strpos($key, 'accept') === 0 &&
            strpos($key, 'accept-control-') !== 0
        );
    }

    private static function parseQValue($value)
    {
        $qvalue = str_replace('q=', '', $value);

        if (is_numeric($qvalue) === false) {
            throw new Exception('Header contains a q-factor non numeric: "' . $value . '"', 3);
        } else if ($qvalue > 1) {
            throw new Exception('Header contains a q-factor greater than 1 (value of q parameter can be from 0 to 1): "' . $value . '"', 3);
        }

        return floatval($qvalue);
    }
}
