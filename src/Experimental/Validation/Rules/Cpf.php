<?php
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
        if (empty($rules) === false) {
            throw new Exception('Invalid combination of rules: ' . get_class($this));
        }

        parent::exec($path, $value, $param, $rules);

        if (preg_match('/^(?:\d{11}|\d{3}\.\d{3}\.\d{3}-\d{2})$/D', $value) !== 1) {
            $this->failure = $this->messages['default'];
            return false;
        }

        $cpf = preg_replace('/\D+/', '', $value);

        if (preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            $this->failure = $this->messages['default'];
            return false;
        }

        $base = substr($cpf, 0, 9);
        $dv1 = self::modulo11($base, range(10, 2));
        $dv2 = self::modulo11($base . $dv1, range(11, 2));

        if (substr($cpf, 9) === $dv1 . $dv2) {
            return true;
        }

        $this->failure = $this->messages['default'];

        return false;
    }

    private static function modulo11($base, array $weight)
    {
        $sum = 0;

        foreach (str_split($base) as $i => $char) {
            $sum += (ord($char) - 48) * $weight[$i];
        }

        $rest = $sum % 11;

        return $rest < 2 ? 0 : 11 - $rest;
    }
}
