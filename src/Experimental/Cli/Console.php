<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Cli;

use Inphinit\Event;
use Inphinit\Exception;

class Console
{
    const PREFIX_ERROR = 'Invalid option: \'%s\'. Note: Options require prefix: -- (long) or - (short)';

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
            throw new Exception('Unknown command: ' . $name);
        }

        $command = $this->commands[$name];

        try {
            $response = $command->response(self::getEntries($arguments));
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        if ($response !== null && is_int($response) === false) {
            throw new Exception('Return must be of type int or null, ' . gettype($response) . ' given');
        }

        return $response === null ? 0 : $response;
    }

    /**
     * Shortcut to execute predefined commands or commands from `system/console.php`
     *
     * @param string                $command The command that will be executed
     * @param array<string, string> $args    Argument list
     * @param string                $code    If the $code argument is present, then the return status of
     *                                       the executed command will be written to this variable
     */
    public static function run($command, array $args = array(), &$code = null)
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
            return self::runFromInstance($console, $env, $values, $code);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    private static function runFromInstance($console, $env, $args, &$code)
    {
        if (empty($console)) {
            require_once __DIR__ . '/../../boot_console.php';
        }

        ob_start();

        $code = $console->exec($args);

        return ob_get_clean();
    }

    private static function getEntries(array $entries)
    {
        $output = array();
        $lastOpt = '';

        foreach ($entries as $entry) {
            if (preg_match('/^((-)([a-z0-9]+)|(--)([a-z][\w:\-?]+))$/i', $entry, $matches) === 1) {
                if ($lastOpt !== '') {
                    $output[$lastOpt] = '';
                }

                if (isset($matches[5])) {
                    $lastOpt = $matches[5];
                } else {
                    $shorts = str_split($matches[3]);
                    $lastOpt = array_pop($shorts);
                    $output += array_fill_keys($shorts, '');
                }
            } elseif ($lastOpt !== '') {
                $output[$lastOpt] = $entry;
                $lastOpt = '';
            } else {
                $output[$entry] = '';
            }
        }

        if ($lastOpt !== '') {
            $output[$lastOpt] = '';
        }

        return $output;
    }
}
