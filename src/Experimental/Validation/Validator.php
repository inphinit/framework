<?php
namespace Inphinit\Experimental\Validation;

use Inphinit\App;
use Inphinit\Exception;
use Inphinit\Utility\PropertyAccessor;

class Validator
{
    const MODE_STOP = 1;
    const MODE_THROW = 2;

    private $data;
    private $extendInstances = array();
    private $extendRules = array(
        'cnpj' => 'Inphinit\Experimental\Validation\Rules\Cnpj',
        'cpf' => 'Inphinit\Experimental\Validation\Rules\Cpf',
        'cpfcnpj' => 'Inphinit\Experimental\Validation\Rules\CpfCnpj',
        'ip' => 'Inphinit\Experimental\Validation\Rules\Ip',
        'password' => 'Inphinit\Experimental\Validation\Rules\Password',
    );
    private $failureMessages = array();
    private $messages = array(
        'boolean' => 'Must be a boolean value (on, yes, true, 1, off, no, false or 0)',
        'decimal' => 'Must be a valid decimal number',
        'email' => 'Must be a valid email address',
        'equals' => 'Must match the \'%s\' field',
        'in' => 'Must be one of the allowed values',
        'integer' => 'Must be a valid integer',
        'invalid' => 'Invalid value',
        'max' => 'Enter no more than %s characters',
        'min' => 'Enter at least %s characters',
        'max_numeral' => 'Enter a number no greater than %s',
        'min_numeral' => 'Enter a number greater than or equal to %s',
        'regex' => 'Invalid format',
        'required' => 'This field is required',
        'type' => 'Invalid type: expected a string, number or boolean',
        'unicode_error' => 'Contains invalid Unicode (UTF-8) characters',
        'url' => 'Must be a valid HTTP or HTTPS url',
    );
    private $mode;
    private $stopped = false;

    private static $allowedInBoolean = array('boolean', 'in');
    private static $allowedInDecimal = array('decimal', 'in', 'regex', 'min', 'max');
    private static $allowedInInteger = array('integer', 'in', 'regex', 'min', 'max');
    private static $conversionRules = array('boolean', 'decimal', 'integer');
    private static $rangeRules = array('min', 'max');
    private static $urlRuleScheme = array('https', 'http');

    public function __construct(array $data, $mode = 0)
    {
        $this->data = $data;
        $this->mode = $mode;
    }

    /**
     * Create a Validator instance using $_GET
     *
     * @param int $mode
     * @return \Inphinit\Experimental\Validation\Validator
     */
    public static function get($mode = 0)
    {
        return new static($_GET, $mode);
    }

    /**
     * Create a Validator instance using $_POST
     *
     * @param int $mode
     * @return \Inphinit\Experimental\Validation\Validator
     */
    public static function post($mode = 0)
    {
        return new static($_POST, $mode);
    }

    /**
     * Configures failure messages of standard rules.
     * Note: To customize the messages for additional rules, use the `getRuleInstance()` method.
     *
     * @param string $type
     * @param string $message
     * @return \Inphinit\Experimental\Validation\Validator
     */
    public function setMessage($type, $message)
    {
        if (isset($this->messages[$type]) === false) {
            throw new Exception('Invalid message type');
        }

        if (is_string($message) === false) {
            throw new Exception('Invalid message');
        }

        $new_message = str_replace('%%', '', $message);
        $prev_message = str_replace('%%', '', $this->messages[$type]);
        $prev_count = substr_count($prev_message, '%');

        if ($prev_count !== substr_count($new_message, '%')) {
            throw new Exception("The {$type} message must contain {$prev_count} specifiers");
        }

        $this->messages[$type] = $message;

        return $this;
    }

    /**
     * Set a new rule
     *
     * @param string $name
     * @param string $rule
     * @return \Inphinit\Experimental\Validation\Validator
     */
    public function setRule($name, $rule)
    {
        $this->extendRules[$name] = $rule;
        $this->extendInstances[$name] = null;

        if (App::config('environment') === 'development') {
            try {
                $this->getRuleInstance($name);
            } catch (\Exception $ex) {
                $this->extendRules[$name] = null;
                throw new Exception($ex->getMessage(), 0, 2, $ex);
            }
        }

        return $this;
    }

    /**
     * Rules can be a string separated by `|` or an array
     * (use an array for `regex:` rules, since patterns may contain `|`).
     *
     * Supported rules:
     * - bool (On, Yes, True, 1, Off, No, False, and 0)
     * - decimal (if valid convert to float)
     * - equals:<field>
     * - email (validates only the format)
     * - in:<a,b,c> (unexpected value)
     * - integer (if valid convert to int)
     * - max:<n> (value for decimal and int; length for strings)
     * - min:<n> (value for decimal and int; length for strings)
     * - regex:<pattern> (not match with the expected format)
     * - required (is required)
     * - url (a valid url)
     *
     * @param string       $path
     * @param mixed        &$var
     * @param string|array $rules
     * @param mixed        $default Used when absent/empty and not required.
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Validation\Validator
     */
    public function field($path, &$var, $rules, $default = null)
    {
        $var = null;

        if ($this->stopped) {
            return $this;
        }

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $numeral = false;
        $ruleset = array();
        $value = PropertyAccessor::getValue($path, $this->data);

        if ($value === null || $value === '') {
            if (in_array('required', $rules, true)) {
                $this->appendFailure($this->messages['required'], $path, null);
            } else {
                $var = $default;
            }

            return $this;
        }

        if (is_scalar($value) === false) {
            $this->appendFailure($this->messages['type'], $path, null);
            return $this;
        }

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue;
            }

            if (strpos($rule, ':') !== false) {
                list($rule, $param) = explode(':', $rule, 2);
            } else {
                $param = null;
            }

            if (array_key_exists($rule, $ruleset)) {
                throw new Exception('Duplicate rule: ' . $rule);
            }

            $ruleset[$rule] = $param;
        }

        if (array_key_exists('in', $ruleset) && (isset($ruleset['in']) === false || strpos($ruleset['in'], ',') === false)) {
            throw new Exception('`in` rule is misconfigured');
        }

        $rules = array_keys($ruleset);

        if (in_array('boolean', $rules, true)) {
            $diff = array_diff($rules, self::$allowedInBoolean);

            if (empty($diff) === false) {
                throw new Exception('`boolean` rule can only be combined with the `in` rule');
            }
        } elseif (in_array('decimal', $rules, true)) {
            $diff = array_diff($rules, self::$allowedInDecimal);
            $numeral = true;

            if (empty($diff) === false) {
                throw new Exception('`decimal` rule can only be combined with the rules: `in`, `min`, `max` and `regex`');
            }
        } elseif (in_array('integer', $rules, true)) {
            $diff = array_diff($rules, self::$allowedInInteger);
            $numeral = true;

            if (empty($diff) === false) {
                throw new Exception('`integer` rule can only be combined with the rules: `in`, `min`, `max` and `regex`');
            }
        }

        // Prioritize rules that do not perform type conversions
        uksort($ruleset, array($this, 'priorityRules'));

        foreach ($ruleset as $rule => $arg) {
            if (isset($this->extendRules[$rule])) {
                unset($ruleset[$rule]);

                $instance = $this->getRuleInstance($rule);
                $value = $instance->exec($path, $value, $arg, $ruleset);

                if ($value === null) {
                    $message = $instance->getFailure();

                    if ($message === null) {
                        $message = $this->messages['invalid'];
                    }

                    $this->appendFailure($message, $path, null);

                    return $this;
                }

                $numeral = is_int($value) || is_float($value);
            } else {
                $value = $this->apply($path, $rule, $arg, $value, $numeral, $info);
            }

            if ($value === null) {
                if ($numeral && $rule === 'max') {
                    $rule = 'max_numeral';
                } elseif ($numeral && $rule === 'min') {
                    $rule = 'min_numeral';
                }

                $this->appendFailure($this->messages[$rule], $path, $info);

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
     * @return array<string, string>
     */
    public function failures()
    {
        return $this->failureMessages;
    }

    /**
     * Get an instance of an extended rule
     *
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Validation\Rule
     */
    public function getRuleInstance($rule)
    {
        if (isset($this->extendInstances[$rule])) {
            $instance = $this->extendInstances[$rule];
        } elseif (isset($this->extendRules[$rule])) {
            $cls = $this->extendRules[$rule];

            if (is_subclass_of($cls, 'Inphinit\Experimental\Validation\Rule')) {
                $instance = new $cls();
                $this->extendInstances[$rule] = $instance;
            } else {
                throw new Exception('Invalid extended rule class');
            }
        } else {
            throw new Exception('Invalid extended rule');
        }

        return $instance;
    }

    private function appendFailure($message, $path, $info)
    {
        if ($info !== null) {
            $message = sprintf($message, $info);
        } else {
            $message = str_replace('%%', '%', $message);
        }

        if ($this->mode & self::MODE_THROW) {
            throw new Exception($path . ': ' . $message, 0, 3);
        }

        if (isset($this->failureMessages[$path]) === false) {
            $this->failureMessages[$path] = $message;
        }

        if ($this->mode & self::MODE_STOP) {
            $this->stopped = true;
        }
    }

    private function priorityRules($a, $b)
    {
        return self::rulePriority($a) - self::rulePriority($b);
    }

    private static function rulePriority($rule)
    {
        if (in_array($rule, self::$rangeRules, true)) {
            return 2;
        }

        if (in_array($rule, self::$conversionRules, true)) {
            return 1;
        }

        return 0;
    }

    private function apply($path, $rule, $arg, $input, $numeral, &$info)
    {
        $flags = FILTER_NULL_ON_FAILURE;
        $info = null;
        $valid = false;

        switch ($rule) {
            case 'boolean':
                if (defined('FILTER_VALIDATE_BOOL')) {
                    $filter = FILTER_VALIDATE_BOOL;
                } else {
                    $filter = FILTER_VALIDATE_BOOLEAN;
                }
                return filter_var($input, $filter, $flags);

            case 'decimal':
                return filter_var($input, FILTER_VALIDATE_FLOAT, array('flags' => $flags, 'options' => array(
                    'decimal' => $arg === null ? '.' : $arg
                )));

            case 'email':
                $valid = filter_var($input, FILTER_VALIDATE_EMAIL);
                break;

            case 'integer':
                return filter_var($input, FILTER_VALIDATE_INT, $flags);

            case 'equals':
                $info = $arg;
                $valid = PropertyAccessor::getValue($arg, $this->data) === $input;
                break;

            case 'in':
                $valid = in_array((string) $input, explode(',', $arg), true);
                break;

            case 'max':
                $info = $arg;

                if ($numeral) {
                    $valid = $input <= $arg;
                } else {
                    $length = $this->stringLength($path, $input);
                    $valid = $length !== false && $length <= $arg;
                }
                break;

            case 'min':
                $info = $arg;

                if ($numeral) {
                    $valid = $input >= $arg;
                } else {
                    $length = $this->stringLength($path, $input);
                    $valid = $length !== false && $length >= $arg;
                }
                break;

            case 'regex':
                $valid = preg_match($arg, $input) === 1;
                break;

            case 'url':
                if (PHP_VERSION_ID < 70300) {
                    // Caution: Provides PHP 7.3+ equivalent behavior on older PHP versions
                    $flags = FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED;
                } else {
                    $flags = FILTER_FLAG_NONE;
                }

                if (
                    filter_var($input, FILTER_VALIDATE_URL, $flags) &&
                    in_array(strtolower(parse_url($input, PHP_URL_SCHEME)), self::$urlRuleScheme, true)
                ) {
                    $valid = true;
                }
                break;

            default:
                throw new Exception('Unknown rule: ' . $rule, 0, 3);
        }

        return $valid ? $input : null;
    }

    private function stringLength($path, $value)
    {
        $length = preg_match_all('/./su', (string) $value);

        if ($length !== false) {
            return $length;
        }

        $this->appendFailure($this->messages['unicode_error'], $path, null);

        return false;
    }
}
