<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use InphinitApp;
use Inphinit\Response as DefaultResponse;

class Response
{
    public function xml($root, $data, $charset = 'UTF-8')
    {
        if (empty($root) || false === ctype_alpha($root)) {
            Exception::raise('First argument in Response::xml requires a string', 2);
            return false;
        }

        if (false === is_array($data)) {
            Exception::raise('Second argument in Response::xml requires a array', 2);
            return false;
        }

        $default  = '<?xml version="1.0"';

        if (is_string($charset)) {
            $default .= ' encoding="' . $charset . '"';
        }

        $default .= '?><' . $root . '></' . $root . '>';

        $xmlElement = new \SimpleXMLElement($default);

        self::generate($data, $xmlElement);

        $resp = new DefaultResponse;

        DefaultResponse::contentType('text/xml');

        $resp->data($xmlElement->asXML());

        $xmlElement = null;
    }

    private static function generate($data, \SimpleXMLElement $xmlNode) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    continue;
                }

                self::generate($value, $xmlNode->addChild($key));
            } elseif (false === empty($key) && false === is_numeric($key)) {
                $xmlNode->addChild($key, htmlspecialchars($value));
            }
        }

        $data = $xmlNode = null;
    }
}
