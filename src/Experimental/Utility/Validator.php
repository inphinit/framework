<?php
namespace Inphinit\Experimental\Utility;

use Inphinit\Exception;
use Inphinit\Utility\PropertyAccessor;

class Validator
{
    const MODE_STOP = 1;
    const MODE_THROW = 2;

    private $data;
    private $failureMessages = array();
    private $messages;
    private $mode;
    private $stopped = false;
    private $booleanRules = array('bool', 'in');
    private $decimalRules = array('decimal', 'in', 'regex', 'min', 'max');
    private $digitRules = array('digit', 'in', 'regex', 'min', 'max');

    private static $checkHttp = array('https', 'http');

    /**
     * Create a Validator instance
     *
     * @param array $data
     * @param int   $mode
     */
    public function __construct(array $data, $mode = 0)
    {
        $this->data = $data;
        $this->mode = $mode;

        $this->messages = array(
            'bool' => '%s can have values such as Yes, True, 1, No, False, and 0',
            'decimal' => '%s expects a decimal format',
            'digit' => '%s expects a digit format',
            'email' => '%s expects a valid email',
            'in' => '%s has an unexpected value',
            'ip' => '%s expects a valid IP',
            'max' => '%s expects a maximum %s of %s',
            'min' => '%s expects a minimum %s of %s',
            'regex' => '%s does not match the expected format',
            'required' => '%s is required',
            'type' => '%s contains an unexpected type',
            'unicode_error' => '%s contains invalid Unicode characters',
            'url' => '%s expects a valid url',
        );
    }

    /**
     * Create a Validator instance using $_GET
     *
     * @param int $mode
     * @return \Inphinit\Experimental\Utility\Validator
     */
    public static function get($mode = 0)
    {
        return new static($_GET, $mode);
    }

    /**
     * Create a Validator instance using $_POST
     *
     * @param int $mode
     * @return \Inphinit\Experimental\Utility\Validator
     */
    public static function post($mode = 0)
    {
        return new static($_POST, $mode);
    }

    /**
     * Configures failure messages
     *
     * @param string $type
     * @param string $message
     * @return \Inphinit\Experimental\Utility\Validator
     */
    public function setMessage($type, $message)
    {
        if (isset($this->messages[$type]) === false) {
            throw new Exception('Invalid message type');
        }

        if (is_string($message) === false || strpos($message, '%') === false) {
            throw new Exception('Invalid message');
        }

        $previous = $this->messages[$type];
        $count = substr_count($previous, '%');

        if ($count !== substr_count($message, '%')) {
            throw new Exception("The {$type} message must contain {$count} specifiers");
        }

        $this->messages[$type] = $message;

        return $this;
    }

    /**
     * Rules can be a string separated by `|` or an array
     * (use an array for `regex:` rules, since patterns may contain `|`).
     *
     * Supported rules:
     * - bool (if valid convert to boolean)
     * - decimal (if valid convert to float)
     * - digit (if valid convert to int)
     * - email (validates only the format)
     * - in:<a,b,c>
     * - max:<n> (value for decimal and digit; length for strings)
     * - min:<n> (value for decimal and digit; length for strings)
     * - regex:<pattern>
     * - required
     * - url (validates only the format)
     *
     * @param string       $path
     * @param mixed        $var
     * @param string|array $rules
     * @param mixed        $default Used when absent/empty and not required.
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Utility\Validator
     */
    public function field($path, &$var, $rules, $default = null)
    {
        if ($this->stopped) {
            $var = null;
            return $this;
        }

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $var = null;

        $value = PropertyAccessor::getValue($path, $this->data);

        if ($value === null || $value === '') {
            if (in_array('required', $rules, true)) {
                $this->appendFailure(sprintf($this->messages['required'], $path));
            } else {
                $var = $default;
            }

            return $this;
        }

        if (is_scalar($value) === false) {
            $this->appendFailure(sprintf($this->messages['type'], $path));
            return $this;
        }

        $ruleset = array();

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue;
            }

            $parts = explode(':', $rule, 2);
            $type = $parts[0];
            $arg = isset($parts[1]) ? $parts[1] : null;

            $ruleset[$type] = $arg;
        }

        $numeral = false;
        $types = array_keys($ruleset);

        if (in_array('bool', $types)) {
            if (empty(array_diff($types, $this->booleanRules)) === false) {
                throw new Exception('`bool` rule can only be combined with the `in` rule');
            }
        } elseif (in_array('decimal', $types)) {
            if (empty(array_diff($types, $this->decimalRules)) === false) {
                throw new Exception('`decimal` rule can only be combined with the rules: `in`, `min`, `max` and `regex`');
            }

            $numeral = true;
        } elseif (in_array('digit', $types)) {
            if (empty(array_diff($types, $this->digitRules)) === false) {
                throw new Exception('`digit` rule can only be combined with the rules: `in`, `min`, `max` and `regex`');
            }

            $numeral = true;
        }

        // Prioritize rules that do not perform type conversions
        uksort($ruleset, array($this, 'priorityRules'));

        foreach ($ruleset as $type => $arg) {
            if ($this->apply($path, $type, $arg, $value, $numeral) === false) {
                $message = $this->messages[$type];

                if ($type === 'max' || $type === 'min') {
                    $message = sprintf($message, $path, $numeral ? 'size' : 'length', $arg);
                } else {
                    $message = sprintf($message, $path);
                }

                $this->appendFailure($message);

                return $this;
            }
        }

        $var = $value;

        return $this;
    }

    /**
     * Returns True if there are no failures; otherwise, returns False
     *
     * @return bool
     */
    public function valid()
    {
        return empty($this->failureMessages);
    }

    /**
     * Returns all error messages
     *
     * @return string[]
     */
    public function failures()
    {
        return $this->failureMessages;
    }

    private function appendFailure($message)
    {
        $this->failureMessages[] = $message;

        if ($this->mode & self::MODE_THROW) {
            throw new Exception($message);
        }

        if ($this->mode & self::MODE_STOP) {
            $this->stopped = true;
        }
    }

    private function priorityRules($a, $b)
    {
        if ($a === $b) {
            return 0;
        }

        if ($a === 'bool' || $a === 'decimal' || $a === 'digit') {
            return 1;
        }

        return -1;
    }

    private function apply($path, $rule, $arg, &$value, $numeral)
    {
        $flags = FILTER_NULL_ON_FAILURE;

        switch ($rule) {
            case 'bool':
                if (defined('FILTER_VALIDATE_BOOL')) {
                    $validate = FILTER_VALIDATE_BOOL;
                } else {
                    $validate = FILTER_VALIDATE_BOOLEAN;
                }

                $value = filter_var($value, $validate, $flags);
                return $value !== null;

            case 'digit':
                $value = filter_var($value, FILTER_VALIDATE_INT, $flags);
                return $value !== null;

            case 'decimal':
                $value = filter_var($value, FILTER_VALIDATE_FLOAT, $flags);
                return $value !== null;

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL, $flags) !== null;

            case 'in':
                return in_array((string) $value, explode(',', $arg), true);

            case 'ip':
                return filter_var($value, FILTER_VALIDATE_IP, $flags) !== null;

            case 'max':
                $current = $numeral ? ((float) $value) : $this->strLength($path, $value);
                return $current !== false && $current <= (float) $arg;

            case 'min':
                $current = $numeral ? ((float) $value) : $this->strLength($path, $value);
                return $current !== false && $current >= (float) $arg;

            case 'regex':
                return preg_match($arg, (string) $value) === 1;

            case 'url':
                if (PHP_VERSION_ID < 70400) {
                    // Caution: Provides PHP 7.4+ equivalent behavior on older PHP versions
                    $flags |= FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED;
                }

                if (
                    filter_var($value, FILTER_VALIDATE_URL, $flags) === null ||
                    in_array(strtolower(parse_url($value, PHP_URL_SCHEME)), self::$checkHttp, true) === false
                ) {
                    $value = null;
                }

                return $value !== null;
        }

        throw new Exception('Unknown rule: ' . $rule, 0, 3);
    }

    private function strLength($path, $value)
    {
        $length = preg_match_all('/./su', (string) $value);

        if ($length !== false) {
            return $length;
        }

        $this->appendFailure(sprintf($this->messages['unicode_error'], $path));

        return false;
    }
}
