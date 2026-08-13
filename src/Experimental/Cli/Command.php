<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Cli;

use Inphinit\Exception;

class Command
{
    const ARG_NO_VALUE = 1;
    const ARG_OPTIONAL = 2;
    const ARG_REQUIRED = 3;

    const REGEX_NAME = '/^[A-Za-z][\w:]*$/';
    const REGEX_LONG = '/^([a-z][\w:\-?]+)$/i';
    const REGEX_SHORT = '/^([a-z0-9?])$/i';

    private $name;
    private $callback;
    private $residual = false;

    private $longs = array();
    private $shorts = array();
    private $modes = array();
    private $formats = array();
    private $descriptions = array();
    private $reclaimeds = array();

    /**
     * Creates a command to be used with an `Inphinit\Cli\Console` instance
     *
     * @param string          $name
     * @param string|callable $callback
     * @throws \Inphinit\Exception
     */
    public function __construct($name, $callback)
    {
        if (is_string($name) === false || preg_match(self::REGEX_NAME, $name) !== 1) {
            throw new Exception('Invalid command name: ' . $name);
        }

        if (is_string($callback) && strpos($callback, '::') !== false) {
            $parsed = explode('::', $callback, 2);

            if (isset($parsed[0][0], $parsed[1][0]) === false) {
                throw new Exception('Invalid command class or method: ' . $callback);
            }

            if (method_exists($parsed[0], $parsed[1]) === false) {
                throw new Exception('Command class or method does not exist: ' . $callback);
            }

            $callback = array(
                'controller' => $parsed[0],
                'method' => $parsed[1]
            );
        } elseif (is_callable($callback) === false) {
            throw new Exception('Callback is not callable: ' . $callback);
        }

        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * Define a option for command
     *
     * @param string $long        Define long option, used with `--` prefix
     * @param string $short       Optional. Define short option, used with `-` prefix
     * @param string $mode        Optional. Define whether the option is optional, required,
     *                            or should be used without a value
     * @param string $format      Optional. Define format excepted of value
     *                            (not work with `ARG_NO_VALUE`)
     * @param string $description Optional. Define a description for option
     * @return \Inphinit\Experimental\Cli\Command
     */
    public function setOption($long, $short = null, $mode = self::ARG_OPTIONAL, $format = null, $description = null)
    {
        if (is_string($long) === false || preg_match(self::REGEX_LONG, $long) !== 1) {
            throw new Exception("Invalid long option: '{$long}'");
        }

        if ($short !== null) {
            if (is_string($short) === false || preg_match(self::REGEX_SHORT, $short, $matches) !== 1) {
                throw new Exception("Invalid short option: '{$short}'");
            }

            $short_index = array_search($short, $this->shorts);

            if ($short_index !== false) {
                $long_option = $this->longs[$short_index];
                throw new Exception("'{$short}' option (short) is already associated with '{$long_option}' option");
            }
        }

        $index = array_search($long, $this->longs);

        if ($index !== false) {
            $this->longs[$index] = $long;
            $this->shorts[$index] = $short;
            $this->modes[$index] = $mode;
            $this->formats[$index] = $format;
            $this->descriptions[$index] = $description;
            $this->reclaimeds[$index] = false;
        } else {
            $this->longs[] = $long;
            $this->shorts[] = $short;
            $this->modes[] = $mode;
            $this->formats[] = $format;
            $this->descriptions[] = $description;
            $this->reclaimeds[] = false;
        }

        return $this;
    }

    /**
     * If set to true, it will allow the command to receive invalid parameters,
     * which will be passed to the third parameter of the callback
     *
     * @param bool $enable
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Cli\Command
     */
    public function enableResidual($enable)
    {
        if (is_bool($enable) === false) {
            throw new Exception('Expected boolean value');
        }

        $this->residual = $enable;

        return $this;
    }

    /**
     * They get the name from the command
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * They obtain the (long) options that the command accepts
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->longs;
    }

    /**
     * They obtain the short options that are equivalent to the (long) options
     *
     * @return array
     */
    public function getShortOptions()
    {
        return $this->shorts;
    }

    /**
     * This method will be used automatically by the `Inphinit\Cli\Console` instance
     *
     * @param array $entries
     * @throws \Inphinit\Exception
     * @return mixed
     */
    public function response(array $entries)
    {
        $callback = $this->callback;
        $params = array_fill_keys($this->longs, null);
        $rest = array();

        foreach ($entries as $entry => $value) {
            $index = false;

            if (strlen($entry) > 2) {
                $index = array_search($entry, $this->longs);
            } elseif (strlen($entry) === 1) {
                $index = array_search($entry, $this->shorts);
            }

            if ($index !== false) {
                $this->checkNoValue($index, $entry, $value);
                $this->checkValueFormat($index, $entry, $value);
                $this->reclaimeds[$index] = true;
                $params[$this->longs[$index]] = $value;
            } elseif ($this->residual) {
                $rest[$entry] = $value;
            } else {
                throw new Exception("Invalid entry: {$entry}");
            }
        }

        $this->checkRequired();

        if (is_array($callback)) {
            $controller = $callback['controller'];
            $method = $callback['method'];
            $callback = array(new $controller(), $method);
        }

        return $callback($this, $params, $rest);
    }

    private function checkNoValue($index, $option, $value)
    {
        if ($this->modes[$index] === self::ARG_NO_VALUE && $value !== '') {
            throw new Exception("The '{$option}' option does not accept values, yet '{$value}' was provided", 0, 3);
        }
    }

    private function checkValueFormat($index, $option, $value)
    {
        $format = $this->formats[$index];

        if ($format && preg_match($format, $value) !== 1) {
            throw new Exception("Invalid value format in: {$option}={$value} ($format)", 0, 3);
        }
    }

    private function checkRequired()
    {
        foreach ($this->modes as $index => $mode) {
            if ($mode === self::ARG_REQUIRED && $this->reclaimeds[$index] === false) {
                $long = $this->longs[$index];

                $message = "Missing option: --{$long}";

                $short = $this->shorts[$index];

                if ($short !== null) {
                    $message .= " or -{$short}";
                }

                throw new Exception($message, 0, 3);
            }
        }
    }
}
