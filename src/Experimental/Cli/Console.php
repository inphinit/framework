<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Cli;

use Inphinit\Diagnostics\Inspector;
use Inphinit\Event;
use Inphinit\Exception;

class Console
{
    const LAST_OPTION_REGEX = '/^((-)([a-z0-9]+)|(--)([a-z][\w:\-?]+))$/i';

    protected $namespacePrefix = '\\Commands\\';

    private $commands = array();

    /**
     * Register callable, console controller, and Command instance for a CLI command
     *
     * @param string                                             $name
     * @param string|callable|\Inphinit\Experimental\Cli\Command $callback
     * @return \Inphinit\Experimental\Cli\Command
     */
    public function action($name, $callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $command = new Command($name, $this->namespacePrefix . $callback);
        } elseif ($callback instanceof Command) {
            $command = $callback;
        } else {
            $command = new Command($name, $callback);
        }

        $this->commands[$name] = $command;

        return $command;
    }

    /**
     * Prefixes the namespace to command controller classes
     *
     * @param string $prefix
     */
    public function setNamespace($prefix)
    {
        $this->namespacePrefix = '\\' . $prefix . '\\';
    }

    /**
     * Receives the arguments to execute the command
     * If the command is not found, an exception will be thrown
     *
     * @param array $arguments Define `$argv` without changes
     * @throws \Inphinit\Exception
     * @return int
     */
    public function exec(array $arguments)
    {
        if (empty($arguments[1])) {
            throw new Exception('Missing command name');
        }

        // Remove the first argument "$arguments[0]"
        // which is always the name that was used to execute the script.
        array_shift($arguments);

        // Command name
        $name = array_shift($arguments);

        if (empty($this->commands[$name])) {
            throw new Exception("Unknown command: {$name}");
        }

        $command = $this->commands[$name];

        try {
            $response = $command->response(self::getEntries($arguments));
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode(), 2, $ex);
        }

        if ($response !== null) {
            if (is_int($response) === false) {
                $type = Inspector::type($response);
                throw new Exception("Return must be of type int or null, {$type} given");
            }

            if ($response < 0 || $response > 254) {
                throw new Exception('Exit codes should be in the range 0 to 254');
            }

            return $response;
        }

        return 0;
    }

    /**
     * Shortcut to execute predefined commands or commands from `system/console.php`
     *
     * @param string                $command The command that will be executed
     * @param array<string, string> $args    Argument list
     * @param int                   $code    If the $code argument is present, then the return status of
     *                                       the executed command will be written to this variable
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function run($command, array $args = array(), &$code = 0)
    {
        global $console;
        global $env;

        $values = array('', $command);

        foreach ($args as $key => $value) {
            $key = ltrim(trim($key), '-');

            if (strlen($key) === 1) {
                $key = '-' . $key;
            } else {
                $key = '--' . $key;
            }

            $values[] = $key;

            if ($value !== null) {
                $values[] = $value;
            }
        }

        try {
            return self::getOutput($console, $env, $values, $code);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }
    }

    private static function isNotConsole($console)
    {
        return ($console instanceof Console) === false;
    }

    private static function getOutput($console, $env, $args, &$code)
    {
        if (self::isNotConsole($console)) {
            require_once __DIR__ . '/../../boot_console.php';
        }

        if (self::isNotConsole($console)) {
            throw new Exception('Console instance could not be found; there is probably an error in the framework installation');
        }

        if (ob_start() === false) {
            throw new Exception('Failed to buffer command output');
        }

        $code = $console->exec($args);

        return ob_get_clean();
    }

    private static function getEntries(array $entries)
    {
        $output = array();
        $last_option = '';

        foreach ($entries as $entry) {
            if (preg_match(self::LAST_OPTION_REGEX, $entry, $matches) === 1) {
                if ($last_option !== '') {
                    $output[$last_option] = '';
                }

                if (isset($matches[5])) {
                    $last_option = $matches[5];
                } else {
                    $shorts = str_split($matches[3]);
                    $last_option = array_pop($shorts);
                    $output += array_fill_keys($shorts, '');
                }
            } elseif ($last_option !== '') {
                $output[$last_option] = $entry;
                $last_option = '';
            } else {
                $output[$entry] = '';
            }
        }

        if ($last_option !== '') {
            $output[$last_option] = '';
        }

        return $output;
    }
}
