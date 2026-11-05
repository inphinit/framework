<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Validation;

use Inphinit\Exception;

abstract class Rule
{
    protected $failure;
    protected $alloweds = array();
    protected $messages = array();

    /**
     * Executes the rule on a value.
     *
     * @param string      $path   Path of the field being validated
     * @param mixed       &$value Value of the field (scalar); may be sanitized
     * @param string|null $param  Text after `:` in the rule (e.g. `foo` in `rule:foo`), or null if absent
     * @param array       $rules  Other rules applied to the same field (name => parameter)
     * @throws \RuntimeException  If the rule is misconfigured or combined with incompatible rules
     * @return bool               True if valid; false otherwise.
     */
    abstract public function exec($path, &$value, $param, array $rules);

    /**
     * Check if the rules are accepted
     *
     * @param array $rules
     * @throws \RuntimeException
     */
    final protected function isAllowed(array $rules)
    {
        $diff = array_diff(array_keys($rules), $this->alloweds);

        if (empty($diff) === false) {
            throw new \RuntimeException('Cannot be combined with ' . implode('|', $diff));
        }

        $this->failure = null;
    }

    /**
     * Validate after set $failure
     *
     * @return bool
     */
    final protected function passed()
    {
        return $this->failure === null;
    }

    /**
     * Returns a string if a failure occurs; otherwise, returns null
     *
     * @return string|null
     */
    final public function getFailure()
    {
        return $this->failure;
    }

    /**
     * @param string $type    Message type
     * @param string $message Message
     * @throws \Inphinit\Exception
     */
    final public function setMessage($type, $message)
    {
        if (isset($this->messages[$type]) === false) {
            throw new Exception('Invalid message type');
        }

        if (is_string($message) === false) {
            throw new Exception('Invalid message');
        }

        $this->messages[$type] = $message;
    }
}
