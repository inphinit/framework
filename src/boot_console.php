<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\App;
use Inphinit\Experimental\Cli\Command;
use Inphinit\Experimental\Cli\Console;
use Inphinit\Experimental\Scheduling\Scheduler;
use Inphinit\Experimental\Storage;

require_once __DIR__ . '/boot.php';
require_once __DIR__ . '/env_vars.php';

/** @var Inphinit\Experimental\Environment\EnvFile $env */

$console = new Console();
$scheduler = new Scheduler();

$scheduler->setBackgroundCommand(escapeshellarg(INPHINIT_ROOT . '/run') . ' --task %s');
$scheduler->setLockFile(INPHINIT_SYSTEM . '/storage/schedule.lock');
$scheduler->setStateFile(INPHINIT_SYSTEM . '/storage/schedule.json');

$console->action('schedule:enable', function (Command $command, array $options, array $residues) {
    // Caution: In CLI, the binary path is always returned correctly (failures usually occur in FPM).
    $php_bin = PHP_BINARY;
    $script = INPHINIT_ROOT . '/run';
    $log = INPHINIT_SYSTEM . '/storage/logs/schedule.log';

    // Stable marker used to find/replace this entry on repeated runs
    $marker = 'managed-by-inphinit-schedule';

    $php_bin = escapeshellarg($php_bin);
    $script = escapeshellarg($script);
    $log = escapeshellarg($log);
    $filter = escapeshellarg('# ' . $marker);

    $expr = "* * * * * {$php_bin} {$script} schedule:run >> {$log} 2>&1 {$marker}";
    $execute = "(crontab -l 2>/dev/null | grep -Fv {$filter}; echo {$expr}) | crontab -";

    echo "> {$execute}\n";

    \passthru($execute, $code);

    if ($code === 0) {
        echo "\nSchedule enabled.\n";
    } else {
        echo "\nError {$code}\n";

        return $code;
    }
})->restrictToCli(true);

$console->action('schedule:disable', function (Command $command, array $options, array $residues) {
    // Stable marker used to find/replace this entry on repeated runs
    $marker = 'managed-by-inphinit-schedule';

    $filter = escapeshellarg('# ' . $marker);
    $execute = "(crontab -l 2>/dev/null | grep -Fv {$filter}) | crontab -";

    echo "> {$execute}\n";

    \passthru($execute, $code);

    if ($code === 0) {
        echo "\nSchedule disabled.\n";
    } else {
        echo "\nError {$code}\n";
    }

    return $code;
})->restrictToCli(true);

$console->action('schedule:run', function (Command $command, array $options, array $residues) use ($scheduler) {
    $task = $options['task'];

    if ($task === null) {
        $executed = $scheduler->exec();

        echo "{$executed} tasks were performed.\n";

        return 0;
    }

    $scheduler->runTask($task);
})->setOption('task', 't', 0, null, 'Execute a specific task')->restrictToCli(true);

$console->action('app:down', function (Command $command, array $options, array $residues) {
    if (App::down()) {
        echo 'Maintenance mode is now active.';
    } else {
        echo 'Unable to activate maintenance mode.';
        return 1;
    }
});

$console->action('app:up', function (Command $command, array $options, array $residues) {
    if (App::up()) {
        echo 'The application is active, and maintenance mode has been disabled.';
    } else {
        echo 'Unable to deactivate maintenance mode.';
        return 1;
    }
});

$console->action('env:boot', function (Command $command, array $options, array $residues) use ($env) {
    if (isset($options['override'])) {
        $env->setOverride(true);
    }

    if ($env->storeAsVars(INPHINIT_SYSTEM . '/boot/env.php')) {
        echo 'Optimized `.env` with caching on boot.';
    } else {
        echo 'Unable to optimize `.env`.';
        return 1;
    }
})->setOption('override', 'o', Command::ARG_NO_VALUE, null, 'Define override mode');

$console->action('env:source', function (Command $command, array $options, array $residues) {
    $env_file = INPHINIT_SYSTEM . '/boot/env.php';

    if (is_file($env_file) === false) {
        echo 'Optimization of the `.env` is already disabled';
    } elseif (unlink($env_file)) {
        echo 'Disabled `.env` optimization at boot.';
    } else {
        echo 'Unable to disable `.env` optimization.';
        return 1;
    }
});

$console->action('pkg:up', function (Command $command, array $options, array $residues) {
    require_once INPHINIT_SYSTEM . '/boot/importpackages.php';
});

$serve = $console->action('serve', function (Command $command, array $options, array $residues) {
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

    // passthru($execute, $code);

    $descriptor_spec = array(STDIN, STDOUT, STDERR);

    $handle = proc_open($execute, $descriptor_spec, $pipes);

    do {
        sleep(1);
        $status = proc_get_status($handle);
        $code = $status['exitcode'];
    } while ($status['running']);

    proc_close($handle);

    return $code;
});

$serve->setOption('host', 'h', 0, null, 'Define server address');
$serve->setOption('port', 'p', 0, null, 'Define server port');
$serve->setOption('vars', 'v', 0, '#^[EGPCS]+$#', 'Define variables order');
$serve->setOption('conf', 'c', 0, null, 'Define php.ini path');
$serve->restrictToCli(true);

$console->action('session:clear', function (Command $command, array $options, array $residues) {
    $max_inactive = App::config('session_max_inactive');

    if ($max_inactive === null || ctype_digit($max_inactive) === false || $max_inactive[0] === '0' || $max_inactive < 1) {
        echo 'Environment variable APP_SESSION_MAX_INACTIVE is missing or has an invalid value';
        return -1;
    }

    $expires = time() - $max_inactive;
    $attempts = $options['attempts'] === null ? 20 : intval($options['attempts']);

    $affecteds = Storage::clear('session', $expires, $attempts, function ($filename) {
        return strpos($filename, '~sess') === 0;
    });

    echo $affecteds . ' session files were removed';
})->setOption('attempts', 'a', 0, '#^[1-9](\d*?)$#', 'Define number attempts (Default: 20)')->restrictToCli(true);

// system/console.php
require INPHINIT_SYSTEM . '/console.php';
