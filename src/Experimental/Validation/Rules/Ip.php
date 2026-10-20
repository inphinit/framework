<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Experimental\Validation\Rule;

class Ip extends Rule
{
    private $messages = array(
        'default' => 'Invalid IP',
    );

    public function exec($path, &$value, array $params, array $rules)
    {
        parent::exec($path, $value, $params, $rules);

        $flags = FILTER_NULL_ON_FAILURE;

        if (in_array('v4', $params, true)) {
            $flags |= FILTER_FLAG_IPV4;
        }

        if (in_array('v6', $params, true)) {
            $flags |= FILTER_FLAG_IPV6;
        }

        if (in_array('no_private', $params, true)) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        if (in_array('no_reserved', $params, true)) {
            $flags |= FILTER_FLAG_NO_RES_RANGE;
        }

        if (filter_var($value, FILTER_VALIDATE_IP, $flags) !== null) {
            return true;
        }

        $this->failure = $this->messages['default'];

        return false;
    }
}
