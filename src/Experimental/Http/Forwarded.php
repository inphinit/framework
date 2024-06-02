<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\App;
use Inphinit\Http\Request;

class Forwarded
{
    private $params = array(
        'by' => [],
        'for' => [],
        'host' => '',
        'proto' => '',
    );

    public function __construct($header = null, $fallback = true)
    {
        // Forwarded: by=<identifier>;for=<identifier>;host=<host>;proto=<http|https>

        if ($header) {
            $forwarded = $header;
        } else {
            $forwarded = Request::header('forwarded');
        }

        if (!$forwarded) {
            throw new Exception('Error Processing Request', 0, 2);
        }

        // foreach (explode(';', $forwarded) as $item) {
        //     $item = explode('=', $item, 2);
        //     $this->appendParam(strtolower($item[0]), $item[1]);
        // }
    }

    public function setHost($host)
    {
        $this->host = $host;
    }

    public function setProto($proto)
    {
        if ($proto === 'https' || $proto === 'http') {
            $this->proto = $proto;
        }
    }

    public function toHeader()
    {
        $output = '';

        if ($this->params['by']) {
            $output .= 'by=' . $this->params['by'];
        }

        if ($this->params['for']) {
            $output .= 'for=' . $this->params['for'];
        }

        if ($this->params['host']) {
            $output .= 'host=' . $this->params['host'];
        }

        if ($this->params['proto']) {
            $output .= 'proto=' . $this->params['proto'];
        }

        return 'Forwarded: ' . $output;
    }

    private function appendParam($param, $value)
    {
        if ($param === 'host') {
            $this->setHost($value);
        } elseif ($param === 'proto') {
            $this->setProto($value);
        } elseif ($param === 'by' || $param === 'for') {
            foreach (explode(',', $value) as $item) {
            }
        }
    }
}
