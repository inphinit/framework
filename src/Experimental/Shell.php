<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Experimental;

class Shell
{
    private $io;
    private $ec;

    private $argc = 0;
    private $argv = array();

    public function __destruct()
    {
        $this->io = null;
    }

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

    public function arguments()
    {
        return $this->argv;
    }

    public function hasArgs()
    {
        return $this->argc !== 0;
    }

    public static function isCli()
    {
        return php_sapi_name() === 'cli';
    }

    public static function input($length = 1024)
    {
        if (self::isCli()) {
            return fgets(STDIN, $length);
        }
    }

    public function inputObserver($callback, $exitCicle = 'exit')
    {
        if (self::isCli() === false || is_callable($callback) === false) {
            return false;
        }

        $this->ec = $exitCicle;

        $this->io = $callback;
        $this->fireInputObserver();
    }

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
