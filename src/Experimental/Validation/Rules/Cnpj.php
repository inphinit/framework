<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class Cnpj extends Rule
{
    private $messages = array(
        'default' => 'Invalid CNPJ',
    );

    public function exec($path, &$value, array $params, array $rules)
    {
        if (empty($rules) === false) {
            throw new Exception('Invalid combination of rules');
        }

        parent::exec($path, $value, $params, $rules);

        $cnpj = strtoupper($value);

        $format = '/^(?:[A-Z0-9]{12}\d{2}|[A-Z0-9]{2}\.[A-Z0-9]{3}\.[A-Z0-9]{3}\/[A-Z0-9]{4}-\d{2})$/D';

        if (preg_match($format, $cnpj) !== 1) {
            $this->failure = $this->messages['default'];
            return false;
        }

        $cnpj = preg_replace('/[^A-Z0-9]+/', '', $cnpj);

        if (preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            $this->failure = $this->messages['default'];
            return false;
        }

        $base = substr($cnpj, 0, 12);
        $dv1 = self::modulo11($base, array_merge(range(5, 2), range(9, 2)));
        $dv2 = self::modulo11($base . $dv1, array_merge(range(6, 2), range(9, 2)));

        if (substr($cnpj, 12) === $dv1 . $dv2) {
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
