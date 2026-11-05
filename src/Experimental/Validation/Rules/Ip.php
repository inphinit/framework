<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Experimental\Validation\Rule;

class Ip extends Rule
{
    protected $messages = array(
        'default' => 'Invalid IP',
    );

    public function exec($path, &$value, $param, array $rules)
    {
        $this->isAllowed($rules);

        $flags = FILTER_NULL_ON_FAILURE;

        if ($param !== null) {
            foreach (explode(',', $param) as $item) {
                if ($item === 'v4') {
                    $flags |= FILTER_FLAG_IPV4;
                } elseif ($item === 'v6') {
                    $flags |= FILTER_FLAG_IPV6;
                } elseif ($item === 'no_private') {
                    $flags |= FILTER_FLAG_NO_PRIV_RANGE;
                } elseif ($item === 'no_reserved') {
                    $flags |= FILTER_FLAG_NO_RES_RANGE;
                } else {
                    throw new \RuntimeException('Invalid param: ' . $item);
                }
            }
        }

        if (filter_var($value, FILTER_VALIDATE_IP, $flags) === null) {
            $this->failure = $this->messages['default'];
        }

        return $this->passed();
    }
}
