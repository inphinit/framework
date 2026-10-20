<?php
namespace Inphinit\Experimental\Validation\Rules;

use Inphinit\Exception;
use Inphinit\Experimental\Validation\Rule;

class Ip extends Rule
{
    private $messages = array(
        'ipv4' => 'Invalid IPv4',
        'ipv6' => 'Invalid IPv6',
        'no_priv_range' => 'Contains private address',
        'no_res_range' => 'Contains reserved address',
    );

    public function exec($path, &$value, $params, array $rules)
    {
        $flags = FILTER_NULL_ON_FAILURE;

        if (is_array($args)) {
            if (in_array('ipv4', $args, true)) {
                $flags |= FILTER_FLAG_IPV4;
            }

            if (in_array('ipv6', $args, true)) {
                $flags |= FILTER_FLAG_IPV6;
            }

            if (in_array('no_private', $args, true)) {
                $flags |= FILTER_FLAG_NO_PRIV_RANGE;
            }

            if (in_array('no_reserved', $args, true)) {
                $flags |= FILTER_FLAG_NO_RES_RANGE;
            }
        }

        return filter_var($value, FILTER_VALIDATE_IP, $flags) !== null;
    }

    public function getFailure()
    {
        return $this->failure;
    }
}
