<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Shell
{
    private $argc = 0;
    private $argv = array();

    /**
     * Create a Shell instance for use CLI interface
     *
     * @return void
     */
    public function __construct()
    {
        if (self::isCli()) {
            global $argc, $argv;

            $this->argc = is_int($argc) ? ($argc - 1) : 0;

            if (is_array($argv)) {
                $arguments = $argv;

                array_shift($arguments);

                $this->argv = $arguments;

                $arguments = null;
            }
        }
    }

    /**
     * Get arguments
     *
     * @return array
     */
    public function arguments()
    {
        return $this->argv;
    }

    /**
     * Check if using arguments
     *
     * @return bool
     */
    public function hasArgs()
    {
        return $this->argc !== 0;
    }

    /**
     * Check if script is executed in CLI
     *
     * @return bool
     */
    public static function isCli()
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Get input data
     *
     * @param int $length
     * @return string|bool
     */
    public static function input($length = 0)
    {
        if (self::isCli()) {
            return $length > 0 ? fgets(STDIN, $length) : fgets(STDIN);
        }

        return false;
    }

    /**
     * Add callback event to input
     *
     * @param callable $callback
     * @return bool
     */
    public function observer($callback)
    {
        if (self::isCli() === false || is_callable($callback) === false) {
            return false;
        }

        while ($response = rtrim(self::input(), PHP_EOL)) {
            if ($callback($response) === false) {
                break;
            }

            usleep(100);
        }

        return true;
    }

    public function __destruct()
    {
        $this->argv = null;
    }
}
