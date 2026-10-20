<?php
namespace Inphinit\Experimental\Validation;

abstract class Rule
{
    protected $failure;
    protected $messages = array();

    /**
     * @param string $path   Path being validated
     * @param string &$value Path value
     * @param string $param  Parameter
     * @param array  $rules  Other rules
     * @return mixed         Returns the sanitized value
     */
    public function exec($path, &$value, $param, array $rules)
    {
        $this->failure = null;
    }

    /**
     * @return string Returns the error message
     */
    public function getFailure()
    {
        return $this->failure;
    }

    /**
     * @param string $type    Message type
     * @param string $message Message
     */
    public function setMessage($type, $message)
    {
        if (isset($this->messages[$type])) {
            $this->messages[$type] = $message;
        } else {
            throw new Exception('Invalid message type');
        }
    }
}
