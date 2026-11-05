<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class Cpf extends Rule
{
    protected $messages = array(
        'default' => 'Invalid CPF',
    );

    public function exec($path, &$value, $param, array $rules)
    {
        $this->isAllowed($rules);

        $value = (string) $value;

        if (preg_match('/^(?:\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2})$/D', $value) !== 1) {
            $this->failure = $this->messages['default'];
            return $this->passed();
        }

        $cpf = preg_replace('/\D+/', '', $value);

        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            $this->failure = $this->messages['default'];
        } else {
            $base = substr($cpf, 0, 9);
            $dv1 = self::modulo11($base, array(10, 9, 8, 7, 6, 5, 4, 3, 2));
            $dv2 = self::modulo11($base . $dv1, array(11, 10, 9, 8, 7, 6, 5, 4, 3, 2));

            if (substr($cpf, 9) !== $dv1 . $dv2) {
                $this->failure = $this->messages['default'];
            }
        }

        return $this->passed();
    }

    private static function modulo11($base, array $weight)
    {
        $sum = 0;

        foreach (str_split($base) as $i => $char) {
            $sum += (ord($char) - 48) * $weight[$i];
        }

        $rest = $sum % 11;

        return $rest < 2 ? '0' : (11 - $rest);
    }
}
