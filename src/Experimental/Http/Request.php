<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Experimental\Exception;
use Inphinit\Experimental\Dom\Document;
use Inphinit\Experimental\Dom\DomException;

/**
 * Constant with the most common HTTP codes
 */
class Request extends \Inphinit\Http\Request
{
    /**
     * Get a value input handler
     *
     * @param bool $array
     * @return array|stdClass|null
     */
    public static function json($array = false)
    {
        $data = file_get_contents('php://input');
        
        if (!$data) return null;

        $json = json_decode($data, $array);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $data = null;
                return $json;

            case JSON_ERROR_DEPTH:
                throw new Exception('The maximum stack depth has been exceeded', 2);

            case JSON_ERROR_STATE_MISMATCH:
                throw new Exception('Invalid or malformed JSON', 2);

            case JSON_ERROR_CTRL_CHAR:
                throw new Exception('Control character error, possibly incorrectly encoded', 2);

            case JSON_ERROR_SYNTAX:
            default:
                throw new Exception('Syntax error', 2);
        }
    }

    /**
     * Get a value input handler
     *
     * @return \Inphinit\Experimental\Dom\Document
     */
    public static function xml()
    {
        $doc = new Document;

        $doc->load('php://input');

        $data = null;

        return $doc;
    }
}
