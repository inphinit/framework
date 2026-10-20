<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class CpfCnpj extends Rule
{
    private $messages = array(
        'default' => 'Invalid document',
    );

    public function exec($path, &$value, array $params, array $rules)
    {
        if (empty($rules) === false) {
            throw new Exception('Invalid combination of rules');
        }

        parent::exec($path, $value, $params, $rules);

        $cpf = new Cpf();

        if ($cpf->exec($path, $value, $params, $rules)) {
            return true;
        }

        $cnpj = new Cnpj();

        if ($cnpj->exec($path, $value, $params, $rules)) {
            return true;
        }

        $this->failure = $this->messages['default'];

        return false;
    }
}
