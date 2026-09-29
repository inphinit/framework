<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Utility;

use Inphinit\Diagnostics\Inspector;
use Inphinit\Exception;

class Strings
{
    private static $onlyAscii;

    /**
     * Convert string to ASCII
     *
     * @param string $text
     * @return string
     */
    public static function ascii($text)
    {
        if (self::$onlyAscii === null) {
            self::$onlyAscii = \Transliterator::create('Any-Latin; Latin-ASCII; [:^ASCII:] Remove');
        }

        return self::$onlyAscii->transliterate($text);
    }

    /**
     * Convert string to camelCase
     *
     * @param string $text
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function camel($text)
    {
        $words = self::getWords($text);

        foreach ($words as $index => &$word) {
            $word = strtolower($word);

            if ($index !== 0) {
                $word = ucfirst($word);
            }
        }

        return implode('', $words);
    }

    /**
     * Convert string to kebab-case
     *
     * @param string $text
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function kebab($text)
    {
        return strtolower(implode('-', self::getWords($text)));
    }

    /**
     * Convert string to PascalCase
     *
     * @param string $text
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function pascal($text)
    {
        $words = self::getWords($text);

        foreach ($words as &$word) {
            $word = ucfirst(strtolower($word));
        }

        return implode('', $words);
    }

    /**
     * Convert string to snake_case
     *
     * @param string $text
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function snake($text)
    {
        return strtolower(implode('_', self::getWords($text)));
    }

    private static function getWords($text)
    {
        if (is_string($text) === false) {
            $type = Inspector::type($text);
            throw new Exception("Expected value to be string, {$type} given", 0, 3);
        }

        $text = trim($text);

        if ($text === '') {
            throw new Exception('Empty string', 0, 3);
        }

        // Acronym boundary: XMLFile -> XML File, parseXMLFile -> parseXML File
        $text = preg_replace('/([A-Z]+?)([A-Z][a-z])/', '$1 $2', $text);

        // Insert a space before every remaining run of uppercase letters
        $text = trim(preg_replace('/([A-Z]+)/', ' $1', $text));

        $entries = array_filter(preg_split('/[\s\-_]+/', $text), 'strlen');

        return array_values($entries);
    }

    /**
     * Generates a hexadecimal string from $length random bytes.
     *
     * - Note: PHP 7+ will use random_bytes().
     * - Note: PHP 5 will use mcrypt.
     *
     * @param int $length
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function random($length = 16)
    {
        if (PHP_VERSION_ID >= 70000) {
            try {
                $bin = \random_bytes($length);
            } catch (\Exception $ex) {
                throw new Exception($ex->getMessage(), 0, 2, $ex);
            }
        } elseif (function_exists('mcrypt_create_iv')) {
            $bin = \mcrypt_create_iv($length, \MCRYPT_DEV_URANDOM);

            if ($bin === false || strlen($bin) !== $length) {
                throw new Exception('MCRYPT: Unable to generate random bytes');
            }
        } else {
            throw new Exception('No supported CSPRNG source is available');
        }

        return \bin2hex($bin);
    }
}
