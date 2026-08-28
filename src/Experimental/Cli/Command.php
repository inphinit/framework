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
    /**
     * @var int The option must not have a value (eg.: `run foo --option`).
     *          Note: The command option remains optional, to change the behavior,
     *          use `Command::ARG_NO_VALUE|Command::ARG_REQUIRED`.
     */
    const ARG_NO_VALUE = 1;

    /** @var int The command requires the option */
    const ARG_REQUIRED = 2;

    const REGEX_NAME = '/^[A-Za-z][\w:]*$/';
    const REGEX_LONG = '/^([a-z][\w:\-]+)$/i';
    const REGEX_SHORT = '/^([a-z0-9?])$/i';

    private $name;
    private $callback;
    private $enabledResidues = false;

    private $longs = array();
    private $shorts = array();
    private $modes = array();
    private $formats = array();
    private $descriptions = array();

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
     * @param string      $long        Define long option, used with `--` prefix
     * @param string|null $short       Optional. Define short option, used with `-` prefix
     * @param int         $modes       Optional. Define whether the option is optional, required,
     *                                 or should be used without a value
     * @param string|null $format      Optional. Define format excepted of value (not work with `ARG_NO_VALUE`)
     * @param string|null $description Optional. Define a description for option
     * @return \Inphinit\Experimental\Cli\Command
     */
    public function setOption($long, $short = null, $modes = 0, $format = null, $description = null)
    {
        if (is_string($long) === false || preg_match(self::REGEX_LONG, $long) !== 1) {
            throw new Exception("Invalid long option");
        }

        if ($short !== null) {
            if (is_string($short) === false || isset($short[1]) || preg_match(self::REGEX_SHORT, $short, $matches) !== 1) {
                throw new Exception("Invalid short option");
            }

            $short_index = array_search($short, $this->shorts);

            if ($short_index !== false) {
                $long_option = $this->longs[$short_index];
                throw new Exception("'{$short}' option (short) is already associated with '{$long_option}' option");
            }
        }

        if ($modes !== 0) {
            $valid_modes = self::ARG_NO_VALUE | self::ARG_REQUIRED;

            if (is_int($modes) === false || ($modes & ~$valid_modes) !== 0) {
                throw new Exception('Invalid mode(s)');
            }
        }

        if ($format !== null && ($modes & self::ARG_NO_VALUE)) {
            throw new Exception('Options that do not expect a value cannot include format validation');
        }

        $index = array_search($long, $this->longs);

        if ($index !== false) {
            $this->longs[$index] = $long;
            $this->shorts[$index] = $short;
            $this->modes[$index] = $modes;
            $this->formats[$index] = $format;
            $this->descriptions[$index] = $description;
        } else {
            $this->longs[] = $long;
            $this->shorts[] = $short;
            $this->modes[] = $modes;
            $this->formats[] = $format;
            $this->descriptions[] = $description;
        }

        return $this;
    }

    /**
     * If set to true, entries that don't match any declared option will be
     * collected as residues and passed to the third parameter of the callback,
     * instead of throwing an exception.
     *
     * @param bool $enable
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Cli\Command
     */
    public function enableResidues($enable)
    {
        if (is_bool($enable) === false) {
            throw new Exception('Expected boolean value');
        }

        $this->enabledResidues = $enable;

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
        $options = array();

        foreach ($this->longs as $index => $long) {
            $short = isset($this->shorts[$index]) ? $this->shorts[$index] : null;
            $options[$long] = $this->resolveOption($long, $short, $index, $entries);

            // Remove the encountered items to retain the residues, if necessary
            unset($entries[$long]);

            if ($short !== null) {
                unset($entries[$short]);
            }
        }

        $callback = $this->callback;

        if (is_array($callback)) {
            $controller = $callback['controller'];
            $method = $callback['method'];
            $callback = array(new $controller(), $method);
        }

        if ($this->enabledResidues || empty($entries)) {
            return $callback($this, $options, $entries);
        }

        $names = array_keys($entries);

        throw new Exception('Unexpected options: ' . implode(', ', $names));
    }

    private function resolveOption($long, $short, $index, $entries)
    {
        $modes = $this->modes[$index];
        $option = '--' . $long;

        if (array_key_exists($long, $entries)) {
            $value = $entries[$long];
        } elseif ($short !== null && array_key_exists($short, $entries)) {
            $value = $entries[$short];
            $option = '-' . $short;
        } elseif ($modes & self::ARG_REQUIRED) {
            $message = "`{$option}`" . ($short ? " (or `-{$short}`)" : '');
            throw new Exception("{$message} is missing", 0, 3);
        } else {
            return null;
        }

        if ($modes & self::ARG_NO_VALUE) {
            if ($value === null) {
                return '';
            }

            throw new Exception("`{$option}` expects a non-value, '{$value}' given", 0, 3);
        }

        if (is_string($value) === false) {
            throw new Exception("`{$option}` expects a value", 0, 3);
        }

        $format = $this->formats[$index];

        if ($format && preg_match($format, $value) !== 1) {
            throw new Exception("Invalid value format in: `{$option} \"{$value}\"` ({$format})", 0, 3);
        }

        return $value;
    }
}
