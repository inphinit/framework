<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Experimental\Validation\Rule;

class Ip extends Rule
{
    protected $messages = array(
        'default' => 'Invalid IP',
    );

    public function exec($path, &$value, $param, array $rules)
    {
        parent::exec($path, $value, $param, $rules);

        $flags = FILTER_NULL_ON_FAILURE;

        if ($param !== null) {
            $args = explode(',', $param);

            if (in_array('v4', $args, true)) {
                $flags |= FILTER_FLAG_IPV4;
            }

            if (in_array('v6', $args, true)) {
                $flags |= FILTER_FLAG_IPV6;
            }

            if (in_array('no_private', $args, true)) {
                $flags |= FILTER_FLAG_NO_PRIV_RANGE;
            }

            if (in_array('no_reserved', $args, true)) {
                $flags |= FILTER_FLAG_NO_RES_RANGE;
            }
        }

        if (filter_var($value, FILTER_VALIDATE_IP, $flags) !== null) {
            return true;
        }

        $this->failure = $this->messages['default'];

        return false;
    }
}
