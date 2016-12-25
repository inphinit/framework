<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

class Shell
{
    private $io;
    private $ec;

    private $argc = 0;
    private $argv = array();

    /**
     * Handle the object's destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->io = null;
    }

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
     * @return string
     */
    public function arguments()
    {
        return $this->argv;
    }

    /**
     * Check if using arguments
     *
     * @return boolean
     */
    public function hasArgs()
    {
        return $this->argc !== 0;
    }

    /**
     * Check if script is executed in CLI
     *
     * @return boolean
     */
    public static function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    /**
     * Get input data
     *
     * @return mixed
     */
    public static function input($length = 0)
    {
        if (self::isCli()) {
            return $length > 0 ? fgets(STDIN, $length) : fgets(STDIN);
        }
    }

    /**
     * Add callback event to input
     *
     * @return void
     */
    public function inputObserver($callback, $exitCicle = 'exit')
    {
        if (self::isCli() === false || is_callable($callback) === false) {
            return false;
        }

        $this->ec = $exitCicle;

        $this->io = $callback;
        $this->fireInputObserver();
    }

    /**
     * Trigger observer input
     *
     * @return void
     */
    protected function fireInputObserver()
    {
        $response = rtrim(self::input(), PHP_EOL);

        if (strcasecmp($response, $this->ec) === 0) {
            return null;
        }

        $callback = $this->io;

        $callback($response);

        usleep(100);

        $this->fireInputObserver();
    }
}
