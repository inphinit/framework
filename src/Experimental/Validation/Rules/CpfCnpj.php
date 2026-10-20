<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class CpfCnpj extends Rule
{
    private $messages = array(
        'default' => 'Invalid document',
    );

    public function exec($path, &$value, $params, array $rules)
    {
        if (empty($rules) === false) {
            throw new Exception('Invalid combination of rules');
        }

        $this->failure = null;

        $cpf = new Cpf();

        if ($cpf->exec($path, $value, $values)) {
            return true;
        }

        $cnpj = new Cnpj();

        if ($cnpj->exec($path, $value, $values)) {
            return true;
        }

        $this->failure = $this->messages['default'];

        return false;
    }

    public function getFailure()
    {
        return $this->failure;
    }
}
