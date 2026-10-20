<?php
namespace Inphinit\Experimental\Validation;

use Inphinit\App;
use Inphinit\Exception;
use Inphinit\Utility\PropertyAccessor;
use Inphinit\Experimental\Validation\RuleInterface;

class Validator
{
    const MODE_STOP = 1;
    const MODE_THROW = 2;

    private $data;
    private $failureMessages = array();
    private $messages;
    private $mode;
    private $stopped = false;
    private $extendInstances = array();
    private $extendRules = array(
        'cnpj' => 'Inphinit\Experimental\Validation\Rules\Cnpj',
        'cpf' => 'Inphinit\Experimental\Validation\Rules\Cpf',
        'cpfcnpj' => 'Inphinit\Experimental\Validation\Rules\CpfCnpj',
        'ip' => 'Inphinit\Experimental\Validation\Rules\Ip',
        'password' => 'Inphinit\Experimental\Validation\Rules\Password',
    );

    private static $booleanRules = array('bool', 'in');
    private static $checkHttp = array('https', 'http');
    private static $decimalRules = array('decimal', 'in', 'regex', 'min', 'max');
    private static $intRules = array('integer', 'in', 'regex', 'min', 'max');
    private static $p1 = array('bool', 'decimal', 'int');
    private static $p2 = array('min', 'max');

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
            'bool' => 'On, Yes, True, 1, Off, No, False, and 0',
            'decimal' => 'decimal format',
            'email' => 'a valid email',
            'in' => 'unexpected value',
            'integer' => 'integer format',
            'max' => 'maximum %s of %s',
            'min' => 'minimum %s of %s',
            'regex' => 'not match with the expected format',
            'required' => 'is required',
            'type' => 'unexpected type',
            'unicode_error' => 'invalid Unicode characters',
            'url' => 'a valid url',
        );
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
     * Configures failure messages
     *
     * @param string $type
     * @param string $message
     * @return \Inphinit\Experimental\Validation\Validator
     */
    public function setMessage($type, $message)
    {
        if (strpos($type, ':') !== false) {
            list($rule, $type) = explode(':', $type, 2);

            $instance = $this->getRuleInstance($rule);
            $instance->setMessage($type, $message);

            return $this;
        }

        if (isset($this->messages[$type]) === false) {
            throw new Exception('Invalid message type');
        }

        if (is_string($message) === false || strpos($message, '%') === false) {
            throw new Exception('Invalid message');
        }

        $prev_message = str_replace('%%', '', $this->messages[$type]);
        $new_message = str_replace('%%', '', $message);

        if (substr_count($prev_message, '%') !== substr_count($new_message, '%')) {
            throw new Exception("The {$type} message must contain {$prev_count} specifiers");
        }

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
     * - email (validates only the format)
     * - in:<a,b,c> (unexpected value)
     * - int (if valid convert to int)
     * - ip (validate IP format)
     * - max:<n> (value for decimal and int; length for strings)
     * - min:<n> (value for decimal and int; length for strings)
     * - regex:<pattern> (not match with the expected format)
     * - required (is required)
     * - type (unexpected type)
     * - unicode_error (invalid Unicode characters)
     * - url (a valid url)
     *
     * @param string       $path
     * @param mixed        $var
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

        $ruleset = array();

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue;
            }

            if (is_string($rule)) {
                $parts = explode(':', $rule, 2);
            } else {
                $parts = $rule;
            }

            $type = $parts[0];
            $arg = isset($parts[1]) ? $parts[1] : null;

            if (isset($ruleset[$type])) {
                throw new Exception('Duplicate rule: ' . $type);
            }

            $ruleset[$type] = $arg;
        }

        $value = PropertyAccessor::getValue($path, $this->data);

        if ($value === null || $value === '') {
            if (in_array('required', $rules, true)) {
                $this->appendFailure('required', array($path));

                return $this;
            }

            if ($default === null) {
                return $this;
            }

            $value = $default;
        }

        if (is_scalar($value) === false) {
            $this->appendFailure('type', array($path));
            return $this;
        }

        if ($this->exec($path, $value, $ruleset)) {
            $var = $value;
        }

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
     * @return array<string, string[]>
     */
    public function failures()
    {
        return $this->failureMessages;
    }

    /**
     * Get an instance of an extended rule
     *
     * @return \Inphinit\Experimental\Validation\Rule
     */
    public function getRuleInstance($rule)
    {
        if (isset($this->extendInstances[$rule])) {
            $instance = $this->extendInstances[$rule];
        } else {
            $instance = new $rule();
            $this->extendInstances[$rule] = $instance;
        }

        return $instance;
    }

    private function exec($path, &$value, $ruleset)
    {
        $types = array_keys($ruleset);

        if (count($types) !== count(array_flip($types))) {
            throw new Exception('There cannot be duplicate rules', 0, 3);
        }

        $extArgs = array();
        $extName = null;
        $extRule = null;
        $instance = null;

        foreach ($this->extendRules as $name => $extend) {
            if (in_array($name, $types, true)) {
                if (isset($ruleset[$name])) {
                    $extArgs = $ruleset[$name];
                }

                $extName = $name;
                $extRule = $extend;
                unset($ruleset[$name]);
                break;
            }
        }

        if ($extRule === null) {
            $numeral = self::checksCombinations($types);
        } else {
            $instance = $this->getRuleInstance($extRule);
        }

        if ($instance !== null) {
            if ($instance->exec($path, $value, $extArgs, $ruleset)) {
                $numeral = is_int($value) || is_float($value);
            } else {
                $this->messages[$extName] = $instance->getFailure();
                $this->appendFailure($extName, array($path));
                return false;
            }
        }

        // Prioritize rules that do not perform type conversions
        uksort($ruleset, array($this, 'priorityRules'));

        foreach ($ruleset as $type => $arg) {
            if ($this->apply($path, $type, $arg, $value, $numeral) === false) {
                if ($type === 'max' || $type === 'min') {
                    $this->appendFailure($type, array($path, $numeral ? 'size' : 'length', $arg));
                } else {
                    $this->appendFailure($type, array($path));
                }

                return false;
            }
        }

        return true;
    }

    private static function checksCombinations($types)
    {
        if (in_array('bool', $types, true)) {
            $rules = array_diff($types, self::$booleanRules);

            if (empty($rules) === false) {
                throw new Exception('`bool` rule can only be combined with the `in` rule', 0, 3);
            }
        } elseif (in_array('decimal', $types, true)) {
            $rules = array_diff($types, self::$decimalRules);

            if (empty($rules) === false) {
                throw new Exception('`decimal` rule can only be combined with the rules: `in`, `min`, `max` and `regex`', 0, 3);
            }

            return true;
        } elseif (in_array('int', $types, true)) {
            $rules = array_diff($types, self::$intRules);

            if (empty($rules) === false) {
                throw new Exception('`int` rule can only be combined with the rules: `in`, `min`, `max` and `regex`', 0, 3);
            }

            return true;
        }

        return false;
    }

    private function appendFailure($type, $args)
    {
        if ($this->stopped === false) {
            $path = $args[0];
            $message = vsprintf($this->messages[$type], $args);

            if (isset($this->failureMessages[$path]) === false) {
                $this->failureMessages[$path] = array($message);
            } else {
                $this->failureMessages[$path][] = $message;
            }

            if ($this->mode & self::MODE_THROW) {
                throw new Exception($message, 0, 3);
            }

            if ($this->mode & self::MODE_STOP) {
                $this->stopped = true;
            }
        }
    }

    private function priorityRules($a, $b)
    {
        return self::rulePriority($a) - self::rulePriority($b);
    }

    private static function rulePriority($rule)
    {
        if (in_array($rule, self::$p2, true)) {
            return 2;
        }

        if (in_array($rule, self::$p1, true)) {
            return 1;
        }

        return 0;
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

            case 'decimal':
                $value = filter_var($value, FILTER_VALIDATE_FLOAT, array(
                    'flags' => $flags,
                    'options' => array('decimal' => $arg === null ? '.' : $arg)
                ));
                return $value !== null;

            case 'int':
                $value = filter_var($value, FILTER_VALIDATE_INT, $flags);
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
                if (PHP_VERSION_ID < 70300) {
                    // Caution: Provides PHP 7.3+ equivalent behavior on older PHP versions
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

        $this->appendFailure('unicode_error', array($path));

        return false;
    }
}
