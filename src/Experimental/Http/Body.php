<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Storage;
use Inphinit\Http\Request;
use Inphinit\Experimental\Dom\Document;

class Body
{
    private static $contentType;

    private $content;

    private $parsed;

    private function __construct() {
        self::loadContentType();

        $this->content = stream_get_contents(Request::raw());
    }

    private static function loadContentType()
    {
        if (self::$contentType === null) {
            self::$contentType = Request::header('Content-Type');
        }
    }

    /**
     * Case-insensitive comparison of two content type headers
     *
     * @param string $needle
     * @param string $haystack
     * @return boolean
     */
    private static function contentTypeCompare($needle, $haystack)
    {
        return
            stripos($haystack, $needle) === 0 && (
                strlen($needle) === strlen($haystack) ||
                in_array(
                    $haystack[strlen($haystack) - 1],
                    array(' ', ';')
                )
            );
    }

    public static function autoParse()
    {
        self::loadContentType();

        if (self::contentTypeCompare('application/json', self::$contentType)) {
            return self::fromJson();
        } elseif (self::contentTypeCompare('application/xml', self::$contentType) || self::contentTypeCompare('text/xml', self::$contentType)) {
            return self::fromXml();
        } elseif (self::contentTypeCompare('multipart/form-data', self::$contentType) || self::contentTypeCompare('application/x-www-form-urlencoded', self::$contentType)) {
            return self::fromFormData();
        } elseif (self::contentTypeCompare('text/plain', self::$contentType)) {
            return self::fromPlainText();
        }

        return false;
    }

    public static function fromJson()
    {
        $body = new Body();

        $body->parsed = json_decode($body->content);

        return $body;
    }

    public static function fromXml($type = Document::MINIMAL)
    {
        $document = new Document();

        $document->loadXML($body->content);

        $body = new Body();

        $body->parsed = $document->toArray($type);

        return $body;
    }

    public static function fromPlainText()
    {
        $body = new Body();

        parse_str($body->content, $body->parsed);

        return $body;
    }

    public static function fromFormData()
    {
        $body = new Body();

        $body->parsed = $_POST;

        return $body;
    }

    public static function fromFormUrlEncoded()
    {
        return self::fromFormData();
    }

    public function get($key, $alternative = null)
    {
        if (empty(self::$parsed)) {
            return $alternative;
        } elseif (strpos($key, '.') === false) {
            return isset(self::$parsed[$key]) ? self::$parsed[$key] : $alternative;
        }

        $data = Helper::extract($key, self::$parsed);

        return $data === null ? $alternative : $data;
    }

    public function filter($keys = array(), $alternatives = array())
    {
        if (count($alternatives) !== 0 && count($keys) !== count($alternatives)) {
            return false;
        }

        $data = array();

        foreach ($keys as $index => $key) {
            $data[$key] = $this->get($key, isset($alternatives[$index]) ? $alternatives[$index] : null);
        }

        return $data;
    }

    public function save($path)
    {
        Storage::write($path, $this->content);
    }

    public function __toString()
    {
        return $this->content;
    }
}