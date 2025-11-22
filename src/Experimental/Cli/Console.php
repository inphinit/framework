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

    private $commands = array();

    protected $namespacePrefix = '\\Commands\\';

    public function __construct()
    {
        if (PHP_SAPI !== 'cli') {
            throw new Exception('This class can only be instantiated in CLI mode');
        }
    }

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
            throw new Exception('Undefined command: ' . $name);
        }

        $command = $this->commands[$name];

        $command->response(self::getOptions($arguments));
    }

    private static function getOptions(array $entries)
    {
        $output = array();
        $lastOpt = '';

        foreach ($entries as $entry) {
            if (preg_match('/^(-([a-z?])|--(\w[\w:]+))$/', $entry, $matches) === 1) {
                if ($lastOpt !== '') {
                    $output[$lastOpt] = '';
                }

                $lastOpt = $matches[1];
            } elseif ($lastOpt === '') {
                throw new Exception(sprintf(self::PREFIX_ERROR, $entry), 0, 3);
            } else {
                $output[$lastOpt] = $entry;
                $lastOpt = '';
            }
        }

        if ($lastOpt !== '') {
            $output[$lastOpt] = '';
        }

        return $output;
    }
}
