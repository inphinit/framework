<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Cli;

use Inphinit\App;
use Inphinit\Experimental\Cli\Console;
use Inphinit\Experimental\Environment\EnvFile;
use Inphinit\Experimental\Scheduling\Scheduler;
use Inphinit\Experimental\Utility\Storage;

class BuiltInCommands
{
    const MARKER_PREFIX = 'inphinit-schedule-';

    const APP_DOWN = 'app:down';
    const APP_UP = 'app:up';
    const CACHE_CLEAR = 'cache:clear';
    const ENV_BOOT = 'env:boot';
    const ENV_SOURCE = 'env:source';
    const PKG_UP = 'pkg:up';
    const SCHEDULE_DISABLE = 'schedule:disable';
    const SCHEDULE_ENABLE = 'schedule:enable';
    const SCHEDULE_RUN = 'schedule:run';
    const SERVE = 'serve';
    const SESSION_CLEAR = 'session:clear';

    private $console;
    private $scheduler;
    private $env;

    public function __construct(Console $console, EnvFile $env, Scheduler $scheduler)
    {
        $this->console = $console;
        $this->env = $env;
        $this->scheduler = $scheduler;
    }

    public function register()
    {
        $this->registerCommand(self::APP_DOWN, 'appDown');

        $this->registerCommand(self::APP_UP, 'appUp');

        $this->registerCommand(self::CACHE_CLEAR, 'cacheClear')
            ->setOption('attempts', 'a', 0, '#^[1-9](\d*?)$#', 'Define number attempts (Default: 20)')
            ->setOption('expires', 'e', 0, '#^[1-9](\d*?)$#', 'Define expires time (Default: 7200)')
            ->restrictToCli(true);

        $this->registerCommand(self::ENV_BOOT, 'envBoot')
            ->setOption('override', 'o', Command::ARG_NO_VALUE, null, 'Define override mode');

        $this->registerCommand(self::ENV_SOURCE, 'envSource');

        $this->registerCommand(self::PKG_UP, 'packageUpdate');

        $this->registerCommand(self::SCHEDULE_DISABLE, 'scheduleDisable')
            ->restrictToCli(true);

        $this->registerCommand(self::SCHEDULE_ENABLE, 'scheduleEnable')
            ->restrictToCli(true);

        $this->registerCommand(self::SCHEDULE_RUN, 'scheduleRun')
            ->setOption('task', 't', 0, null, 'Execute a specific task')
            ->restrictToCli(true);

        $this->registerCommand(self::SERVE, 'serve')
            ->setOption('host', 'h', 0, null, 'Define server address')
            ->setOption('port', 'p', 0, null, 'Define server port')
            ->setOption('vars', 'v', 0, '#^[EGPCS]+$#', 'Define variables order')
            ->setOption('conf', 'c', 0, null, 'Define php.ini path')
            ->restrictToCli(true);

        $this->registerCommand(self::SESSION_CLEAR, 'sessionClear')
            ->setOption('attempts', 'a', 0, '#^[1-9](\d*?)$#', 'Define number attempts (Default: 20)')
            ->restrictToCli(true);
    }

    private function registerCommand($commandName, $callbackMethod)
    {
        $callback = array($this, $callbackMethod);

        return $this->console->action($commandName, function (Command $command, array $options, array $residues) use ($callback) {
            return $callback($command, $options, $residues);
        });
    }

    private function appDown(Command $command, array $options, array $residues)
    {
        if (App::down()) {
            echo 'Maintenance mode is now active.';
        } else {
            echo 'Unable to activate maintenance mode.';
            return 1;
        }
    }

    private function appUp(Command $command, array $options, array $residues)
    {
        if (App::up()) {
            echo 'The application is active, and maintenance mode has been disabled.';
        } else {
            echo 'Unable to deactivate maintenance mode.';
            return 1;
        }
    }

    private function cacheClear(Command $command, array $options, array $residues)
    {
        $expires = $options['expires'] === null ? 7200 : $options['expires'];
        $attempts = $options['attempts'] === null ? 20 : intval($options['attempts']);

        echo 'Cleaning cache files ...', PHP_EOL;

        $affecteds = Storage::clear('cache/output', $expires, $attempts);

        echo $affecteds, ' cache files were removed', PHP_EOL;
    }

    private function envBoot(Command $command, array $options, array $residues)
    {
        if (isset($options['override'])) {
            $this->env->setOverride(true);
        }

        if ($this->env->storeAsVars(INPHINIT_SYSTEM . '/boot/env.php')) {
            echo 'Optimized `.env` with caching on boot.';
        } else {
            echo 'Unable to optimize `.env`.';
            return 1;
        }
    }

    private function envSource(Command $command, array $options, array $residues)
    {
        $env_file = INPHINIT_SYSTEM . '/boot/env.php';

        if (is_file($env_file) === false) {
            echo 'Optimization of the `.env` is already disabled.';
        } elseif (unlink($env_file)) {
            echo 'Disabled `.env` optimization at boot.';
        } else {
            echo 'Unable to disable `.env` optimization.';
            return 1;
        }
    }

    private function packageUpdate(Command $command, array $options, array $residues)
    {
        inphinit_sandbox('boot/importpackages.php');
    }

    private function scheduleDisable(Command $command, array $options, array $residues)
    {
        // Stable marker used to find/replace this entry on repeated runs
        $marker = self::MARKER_PREFIX . 'app';

        $filter = escapeshellarg('# ' . $marker);

        $execute = "(crontab -l 2>/dev/null | grep -Fv {$filter}) | crontab -";

        echo "> {$execute}\n";

        passthru($execute, $code);

        if ($code === 0) {
            echo "\nSchedule disabled.\n";
        } else {
            echo "\nError {$code}\n";
        }

        return $code;
    }

    private function scheduleEnable(Command $command, array $options, array $residues)
    {
        // Caution: In CLI, the binary path is always returned correctly (failures usually occur in FPM).
        $php_bin = PHP_BINARY;
        $script = INPHINIT_ROOT . '/run';
        $log = INPHINIT_SYSTEM . '/storage/logs/schedule.log';

        // Stable marker used to find/replace this entry on repeated runs
        $marker = self::MARKER_PREFIX . 'app';

        $php_bin = escapeshellarg($php_bin);
        $script = escapeshellarg($script);
        $log = escapeshellarg($log);
        $filter = escapeshellarg('# ' . $marker);

        $expr = "* * * * * {$php_bin} {$script} schedule:run >> {$log} 2>&1 # {$marker}";
        $expr = escapeshellarg($expr);

        $execute = "(crontab -l 2>/dev/null | grep -Fv {$filter}; echo {$expr}) | crontab -";

        echo "> {$execute}\n";

        passthru($execute, $code);

        if ($code === 0) {
            echo "\nSchedule enabled.\n";
        } else {
            echo "\nError {$code}\n";

            return $code;
        }
    }

    private function scheduleRun(Command $command, array $options, array $residues)
    {
        $task = $options['task'];

        if ($task === null) {
            $executed = $this->scheduler->exec();

            if ($executed > 0) {
                echo $executed, ' scheduled task(s) were executed.';
            } else {
                echo 'No scheduled task was executed.';
            }

            echo PHP_EOL;

            return 0;
        }

        $this->scheduler->runTask($task);
    }

    private function serve(Command $command, array $options, array $residues)
    {
        $host = $options['host'] !== null ? $options['host'] : App::config('built_in_host');
        $port = $options['port'] !== null ? $options['port'] : App::config('built_in_port');
        $vars = $options['vars'] !== null ? $options['vars'] : 'EGPCS';
        $conf = $options['conf'] !== null ? $options['conf'] : php_ini_loaded_file();

        if (empty($host)) {
            echo 'Empty host';
            return 1;
        }

        if (empty($port) || ltrim($port, '0') === '') {
            echo 'Empty port';
            return 1;
        }

        if (empty($vars)) {
            echo 'Empty vars';
            return 1;
        }

        // Caution: In CLI, the binary path is always returned correctly (failures usually occur in FPM).
        $php_bin = PHP_BINARY;

        $log = escapeshellarg(INPHINIT_SYSTEM . '/storage/logs/errors.log');
        $server = escapeshellarg($host . ':' . $port);
        $public = escapeshellarg(INPHINIT_ROOT . '/public');
        $router = escapeshellarg(INPHINIT_ROOT . '/index.php');
        $vars = escapeshellarg($vars);

        $ini  = $conf ? "-c {$conf} " : '';
        $ini .= "-d variables_order={$vars} -d error_log={$log}";

        $execute = "{$php_bin} {$ini} -S {$server} -t {$public} {$router} 2>&1";

        $descriptor_spec = array(STDIN, STDOUT, STDERR);

        $handle = proc_open($execute, $descriptor_spec, $pipes);

        do {
            sleep(1);
            $status = proc_get_status($handle);
            $code = $status['exitcode'];
        } while ($status['running']);

        proc_close($handle);

        return $code;
    }

    private function sessionClear(Command $command, array $options, array $residues)
    {
        $max = App::config('session_max_inactive');

        if ($max === null || ctype_digit($max) === false || $max[0] === '0' || $max < 1) {
            echo 'The APP_SESSION_MAX_INACTIVE environment variable is missing or has an invalid value', PHP_EOL;
            return -1;
        }

        $expires = time() - $max;
        $attempts = $options['attempts'] === null ? 20 : intval($options['attempts']);

        echo 'Cleaning session files ...', PHP_EOL;

        $affecteds = Storage::clear('session', $expires, $attempts, function ($filename) {
            return strpos($filename, '~sess') === 0;
        });

        echo $affecteds, ' session files were removed', PHP_EOL;
    }
}
