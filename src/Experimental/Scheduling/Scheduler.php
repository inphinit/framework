<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Scheduling;

use Inphinit\Exception;
use Inphinit\Experimental\Cli\Command;
use Inphinit\Experimental\Cli\Console;

class Scheduler
{
    private $backgroundCommand;
    private $lockFile;
    private $lockHandle;
    private $stateFile;
    private $tasks = array();
    private $timeZone;

    /**
     * Create a Scheduler instance
     *
     * @throws \Inphinit\Exception
     */
    public function __construct()
    {
        if (\PHP_SAPI !== 'cli') {
            throw new Exception('The class can only be instantiated in the CLI');
        }

        $this->timeZone = new \DateTimeZone('UTC');
    }

    public function __destruct()
    {
        if ($this->lockHandle !== null) {
            fclose($this->lockHandle);

            $this->lockHandle = null;
        }
    }

    /**
     * Set the PHP script file used to execute background commands (eg.: `/foo/bar/foo.php %s`)
     *
     * @param string $command
     * @throws \Inphinit\Exception
     */
    public function setBackgroundCommand($command)
    {
        if (strpos($command, '%s') === false) {
            throw new Exception('Invalid command sintax');
        }

        $this->backgroundCommand = $command;
    }

    /**
     * Set the lock file (used to prevent repeated executions)
     *
     * @param string $path
     * @throws \Inphinit\Exception
     */
    public function setLockFile($path)
    {
        $dir = dirname($path);

        if (is_dir($dir) === false || is_writable($dir) === false) {
            throw new Exception($path . ' is not writable');
        }

        $this->lockFile = $path;
    }

    /**
     * Set the state file (used to check if a task has already been executed)
     *
     * @param string $path
     * @throws \Inphinit\Exception
     */
    public function setStateFile($path)
    {
        $dir = dirname($path);

        if (is_dir($dir) === false || is_writable($dir) === false) {
            throw new Exception($path . ' is not writable');
        }

        $this->stateFile = $path;
    }

    /**
     * Set timeZone used to control task states
     *
     * @param \DateTimeZone $timeZone
     */
    public function setTimeZone(\DateTimeZone $timeZone)
    {
        $this->timeZone = $timeZone;
    }

    /**
     * Register a callback function to execute
     *
     * @param string $name
     * @param callable $command
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function call($name, callable $callback)
    {
        $task = new Task($callback, $this->timeZone);

        $this->tasks[$name] = $task;

        return $task;
    }

    /**
     * Register a Console command from `system/console.php` to execute
     *
     * @param string  $name
     * @param Command $command
     * @param array   $options
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function command($name, Command $command, array $options = array())
    {
        return $this->call($name, function (Task $task) use ($command, $options) {
            return $command->response($options);
        });
    }

    /**
     * Register a shell command to execute
     *
     * @param string $name
     * @param string $command
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function shell($name, $command)
    {
        return $this->call($name, function (Task $task) use ($command) {
            $last_line = \exec($command, $output, $result_code);

            if ($last_line === false || $result_code !== 0) {
                throw new \RuntimeException(implode(' ', $output), $result_code);
            }

            echo implode(PHP_EOL, $output);
        });
    }

    /**
     * Executes a named task
     *
     * @param string $task
     * @throws \Inphinit\Exception
     */
    public function runTask($name)
    {
        if (isset($this->tasks[$name]) === false) {
            throw new Exception('The task was not found: ' . $name);
        }

        $task = $this->tasks[$name];

        return $task->run();
    }

    /**
     * Runs every due task. This method must be executed during all CRON/schedule calls.
     *
     * @throws \Inphinit\Exception
     * @return int
     */
    public function exec()
    {
        $executed = 0;

        try {
            // Prevents overlapping sweeps if the previous task invocation is still running
            if ($this->lock(true)) {
                $changed = false;

                $state = $this->loadState();

                $now = new \DateTime('now', $this->timeZone);

                foreach ($this->tasks as $name => $task) {
                    $last_run = isset($state[$name]) ? $state[$name] : null;

                    if ($task->isDue($now, $last_run) === false) {
                        continue;
                    }

                    if ($task->isBackground()) {
                        $this->dispatchBackground($name);
                    } else {
                        $task->run();
                    }

                    $state[$name] = $now->getTimestamp();

                    $changed = true;

                    ++$executed;
                }

                if ($changed) {
                    $this->saveState($state);
                }
            }
        } catch(\Exception $ex) {
            $this->lock(false);
            throw new Exception($ex->getMessage(), $ex->getCode(), 2, $ex);
        }

        $this->lock(false);

        return $executed;
    }

    private function lock($enable)
    {
        $handle = $this->lockHandle;

        if ($enable) {
            if ($handle === null) {
                $handle = fopen($this->lockFile, 'c+');

                if ($handle === false) {
                    throw new \RuntimeException('Unable to create lock file: ' . $this->lockFile);
                }

                $this->lockHandle = $handle;
            }

            return flock($handle, LOCK_EX | LOCK_NB);
        } elseif ($handle !== null) {
            return flock($handle, LOCK_UN);
        }

        return false;
    }

    private function loadState()
    {
        $path = $this->stateFile;

        if (is_file($path) === false) {
            return array();
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return array();
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : array();
    }

    private function saveState(array $state)
    {
        $path = $this->stateFile;

        if (file_put_contents($path, json_encode($state), LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write schedule state: ' . $path);
        }
    }

    /**
     * Spawns a detached OS process that re-invokes the CLI entry point to run
     * a single task by name, without blocking the current (scheduler) process
     */
    private function dispatchBackground($name)
    {
        $command = array(
            // eg.: /usr/bin/php
            escapeshellarg(\PHP_BINARY),

            // eg.: /home/project/run schedule:run --task "task_name"
            sprintf($this->backgroundCommand, escapeshellarg($name))
        );

        // eg.: /usr/bin/php /home/project/run schedule:run --task "task_name"
        $exec = implode(' ', $command);

        if (stripos(PHP_OS, 'WIN') === 0) {
            $exec = 'start /B "" ' . $exec;
        } else {
            $exec = $exec . ' > /dev/null 2>&1 &';
        }

        if (\exec($exec) === false) {
            throw new \RuntimeException('Failed to dispatch background command');
        }
    }
}
