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

class CpfCnpj extends Rule
{
    protected $messages = array(
        'default' => 'Invalid document',
    );

    public function exec($path, &$value, $param, array $rules)
    {
        $this->isAllowed($rules);

        $cpf = new Cpf();
        $cpf_value = $value;

        if ($cpf->exec($path, $cpf_value, $param, $rules)) {
            return true;
        }

        $cnpj = new Cnpj();
        $cnpj_value = $value;

        if ($cnpj->exec($path, $cnpj_value, $param, $rules)) {
            return true;
        }

        $this->failure = $this->messages['default'];

        return $this->passed();
    }
}
