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

class Negotiation
{
    private $headers;

    /** Sort headers low to high by q-factors */
    const LOW = 1;

    /** Sort headers high to low by q-factors */
    const HIGH = 2;

    /** Get all values from accept headers (without q-factor) */
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return mixed
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
     * @return mixed
     */
    public static function qFactor($value, $level = self::HIGH)
    {
        $q = explode(',', $value);
        $j = count($q);

        for ($i = 0; $i < $j; $i++) {
            $current = explode(';', $q[$i], 2);

            if (empty($current[1])) {
               $qfactor = 1.0;
            } else {
                $qfactor = floatval( str_replace('q=', '', $current[1]) );
            }

            $q[$i] = array(
                'value' => trim($current[0]),
                'qfactor' => $qfactor
            );
        }

        if ($level === self::ALL) {
            foreach ($q as &$item) {
                $item = $item['value'];
            }

            return $q;
        }

        usort($q, function ($a, $b) use ($level) {
            if ($level === self::LOW) {
                return $a['qfactor'] > $b['qfactor'];
            } else {
                return $a['qfactor'] <= $b['qfactor'];
            }
        });

        return $q;
    }

    private function filter($key)
    {
        return $key === 'te' || (
            $key !== 'accept-ranges' &&
            strpos($key, 'accept') === 0 &&
            strpos($key, 'accept-control-') !== 0
        );
    }
}
