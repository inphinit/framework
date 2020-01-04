<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Experimental\Exception;
use Inphinit\Experimental\Dom\Document;
use Inphinit\Experimental\Dom\DomException;

class Request extends \Inphinit\Http\Request
{
    /**
     * Get a value input handler
     *
     * @param bool $array
     * @throws \Inphinit\Experimental\Exception
     * @return array|stdClass|null
     */
    public static function json($array = false)
    {
        $handle = self::raw();

        if (!$handle) return null;

        $json = json_decode(stream_get_contents($handle), $array);

        fclose($handle);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
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
     * @throws \Inphinit\Experimental\Dom\DomException
     * @return \Inphinit\Experimental\Dom\Document|null
     */
    public static function xml()
    {
        $handle = self::raw();

        if (!$handle) return null;

        $doc = new Document;

        $data = stream_get_contents($handle);

        fclose($handle);

        try {
            $doc->loadXML($data);
        } catch (DomException $ee) {
            throw new DomException($ee->getMessage(), 2);
        }

        $data = null;

        return $doc;
    }
}
