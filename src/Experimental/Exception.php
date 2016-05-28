<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Experimental;

class Exception extends \ErrorException
{
    public static function raise($message, $level = 1, $type = E_ERROR)
    {
        $data = Debug::caller($level);
        throw new static($message, $type, 0, $data['file'], $data['line']);
    }
}
