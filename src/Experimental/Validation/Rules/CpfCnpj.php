<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class CpfCnpj extends Rule
{
    protected $messages = array(
        'default' => 'Invalid document',
    );

    public function exec($path, $value, $param, array $rules)
    {
        if (empty($rules) === false) {
            throw new Exception('Invalid combination of rules: ' . get_class($this));
        }

        parent::exec($path, $value, $param, $rules);

        $cpf = new Cpf();

        $result = $cpf->exec($path, $value, $params, $rules);

        if ($result !== null) {
            return $result;
        }

        $cnpj = new Cnpj();

        $result = $cpf->exec($path, $value, $params, $rules);

        if ($result !== null) {
            return $result;
        }

        $this->failure = $this->messages['default'];

        return null;
    }
}
