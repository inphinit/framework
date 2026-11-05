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

class Cnpj extends Rule
{
    protected $messages = array(
        'default' => 'Invalid CNPJ',
    );

    public function exec($path, &$value, $param, array $rules)
    {
        $this->isAllowed($rules);

        $value = strtoupper((string) $value);
        $cnpj = $value;

        $format = '/^(?:[A-Z0-9]{12}\d{2}|[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}\/[A-Z0-9]{4}-\d{2})$/D';

        if (preg_match($format, $cnpj) !== 1) {
            $this->failure = $this->messages['default'];
            return $this->passed();
        }

        $cnpj = preg_replace('/[^A-Z0-9]+/', '', $cnpj);

        if (preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            $this->failure = $this->messages['default'];
        } else {
            $base = substr($cnpj, 0, 12);
            $dv1 = self::modulo11($base, array(5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2));
            $dv2 = self::modulo11($base . $dv1, array(6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2));

            if (substr($cnpj, 12) !== $dv1 . $dv2) {
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
