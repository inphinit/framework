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
use Inphinit\Experimental\Cli\Console;

class Scheduler
{
    private $lockHandle;
    private $lockPath;
    private $runScript;
    private $statePath;
    private $storage;
    private $tasks = array();

    protected $namespacePrefix = '\\Tasks\\';

    /**
     * Create a Scheduler instance
     *
     * @param string $lockPath  Set the lock file (used to prevent repeated executions)
     * @param string $statePath Set the state file (used to check if a task has already been executed)
     * @param string $runScript Set the PHP script file used to execute background commands
     * @throws \Inphinit\Exception
     */
    public function __construct($lockPath, $statePath, $runScript)
    {
        if (\PHP_SAPI !== 'cli') {
            throw new Exception('The class can only be instantiated in the CLI');
        }

        $this->lockPath = $lockPath;
        $this->statePath = $statePath;
        $this->runScript = $runScript;
    }

    public function __destruct()
    {
        if ($this->lockHandle !== null) {
            fclose($this->lockHandle);
        }
    }

    /**
     * Register callable or controller callback
     *
     * @param string          $name
     * @param string|callable $callback
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function action($name, $callback)
    {
        if (is_string($name) === false || preg_match('/^[a-z]\w*$/i', $name) !== 1) {
            throw new Exception('Invalid name');
        }

        if (isset($this->tasks[$name])) {
            throw new Exception('Task already registered: ' . $name);
        }

        if (is_string($callback) && strpos($callback, '::') !== false) {
            $className = $this->namespacePrefix . $callback;

            list($controller, $method) = explode('::', $className, 2);

            $callback = function (Task $task) use ($controller, $method) {
                $exec = array(new $controller(), $method);
                $exec($task);
            };
        } elseif (is_callable($callback) === false) {
            throw new Exception('Defined callback is not callable');
        }

        $task = new Task($callback);
        $this->tasks[$name] = $task;

        return $task;
    }

    /**
     * Register a shell command to execute
     *
     * @param string $name
     * @param string $command
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function command($name, $command)
    {
        return $this->action($name, function (Task $task) use ($command) {
            $last_line = \exec($command, $output, $result_code);

            if ($last_line === false || $result_code !== 0) {
                throw new \RuntimeException(implode(' ', $output), $result_code);
            }

            return implode(PHP_EOL, $output);
        });
    }

    /**
     * Register a command from `system/console.php` to execute
     *
     * @param string $name
     * @param string $command
     * @param array  $options
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function run($name, $command, array $options = array())
    {
        return $this->action($name, function (Task $task) use ($command, $options) {
            $output = Console::run($command, $options, $code);

            if ($code !== 0) {
                throw new \RuntimeException($output, $code);
            }

            return $output;
        });
    }

    /**
     * Get task using name
     *
     * @param string $task
     * @return \Inphinit\Experimental\Scheduling\Task|null
     */
    public function get($name)
    {
        return isset($this->tasks[$name]) ? $this->tasks[$name] : null;
    }

    /**
     * Prefixes the namespace to task controller classes
     *
     * @param string $prefix
     */
    public function setNamespace($prefix)
    {
        $this->namespacePrefix = '\\' . $prefix . '\\';
    }

    /**
     * Runs every due task. This method must be executed during all CRON/Schedule calls.
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
                $now = new \DateTime();
                $state = $this->loadState();

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
                $handle = fopen($this->lockPath, 'c+');

                if ($handle === false) {
                    throw new \RuntimeException('Unable to create lock file: ' . $this->lockPath);
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
        $path = $this->statePath;

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
        $path = $this->statePath;

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
            escapeshellarg(PHP_BINARY),

            // eg.: /home/project/run
            escapeshellarg($this->runScript),

            'task:run',
            '--task',
            escapeshellarg($name)
        );

        // eg.: /usr/bin/php /home/project/run task:run --task "task_name"
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
