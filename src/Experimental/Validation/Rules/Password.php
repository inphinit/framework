<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class Password extends Rule
{
    protected $messages = array(
        'lower' => 'The password must contain at least one lowercase letter',
        'number' => 'The password must contain at least one number',
        'repeated' => 'The password cannot have the same character repeated 3 or more times in a row',
        'spaces' => 'The password cannot contain spaces',
        'special' => 'The password must contain at least one special character (e.g., !@#$%%)',
        'upper' => 'The password must contain at least one uppercase letter',
    );

    public function exec($path, $value, $param, array $rules)
    {
        parent::exec($path, $value, $param, $rules);

        if (strpbrk($value, '0123456789') === false) {
            $this->failure = $this->messages['number'];
        } elseif (ctype_graph($value) === false) {
            $this->failure = $this->messages['spaces'];
        } elseif (preg_match('/\p{Lu}/u', $value) !== 1) {
            $this->failure = $this->messages['upper'];
        } elseif (preg_match('/\p{Ll}/u', $value) !== 1) {
            $this->failure = $this->messages['lower'];
        } elseif (preg_match('/[^\p{L}\d\s]/u', $value) !== 1) {
            $this->failure = $this->messages['special'];
        } elseif (preg_match('/(.)\1{2,}/u', $value)) {
            $this->failure = $this->messages['repeated'];
        }

        return $this->failure === null ? $value : null;
    }
}
