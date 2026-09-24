<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Scheduling;

use Inphinit\Diagnostics\Inspector;
use Inphinit\Exception;

class Task
{
    /** @var int Task scheduled using crontab-like fields (minute, hour, day, month, weekday) */
    const MODE_CRON = 1;

    /** @var int Task scheduled to run repeatedly every N seconds since its last run */
    const MODE_INTERVAL = 2;

    /** @var int Task scheduled to run exactly once, at or after a given date/time */
    const MODE_ONCE = 3;

    private $background = false;
    private $callback;
    private $cronFields;
    private $intervalSeconds;
    private $mode;
    private $onceAt;

    /**
     * Create a Task instance
     *
     * @param callable $callback Receives the Task instance as first argument
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Schedule the task using crontab-like fields.
     *
     * Each field accepts: `*` (any), a single value, a comma-separated list (`1,2,5`),
     * a range (`1-5`), a step (`*\/5` or `1-30/5`), or a combination of these separated by commas.
     *
     * @param string $minute  0-59
     * @param string $hour    0-23
     * @param string $day     1-31
     * @param string $month   1-12
     * @param string $weekday 0-7 (0 and 7 both mean Sunday)
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function cron($minute, $hour, $day, $month, $weekday)
    {
        $this->cronFields = array(
            self::parseCronField($minute, 0, 59, false),
            self::parseCronField($hour, 0, 23, false),
            self::parseCronField($day, 1, 31, false),
            self::parseCronField($month, 1, 12, false),
            self::parseCronField($weekday, 0, 7, true)
        );

        $this->mode = self::MODE_CRON;

        return $this;
    }

    /**
     * Schedule the task to run repeatedly every given number of seconds,
     * counted from its last execution.
     *
     * @param int $seconds
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function interval($seconds)
    {
        if (is_int($seconds) === false || $seconds < 1) {
            throw new Exception('Interval must be a positive integer (seconds)');
        }

        $this->intervalSeconds = $seconds;
        $this->mode = self::MODE_INTERVAL;

        return $this;
    }

    /**
     * Schedule the task to run exactly once, at (or after) the given date/time.
     * Once executed, it will never run again.
     *
     * @param string|\DateTime $datetime Anything accepted by \DateTime, or a \DateTime instance
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function once($datetime)
    {
        if ($datetime instanceof \DateTime) {
            $dt = $datetime;
        } else {
            try {
                $dt = new \DateTime($datetime);
            } catch (\Exception $ex) {
                throw new Exception('Invalid datetime: ' . $datetime, 0, 2, $ex);
            }
        }

        $this->onceAt = $dt;
        $this->mode = self::MODE_ONCE;

        return $this;
    }

    /**
     * Shortcut for scheduling the task to run daily at a fixed time.
     * Equivalent to `cron($minute, $hour, '*', '*', '*')`.
     *
     * @param string $time Time in `HH:MM` format (24h)
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function at($time)
    {
        if (is_string($time) === false || preg_match('#^([01]?\d|2[0-3]):([0-5]\d)$#', $time, $matches) !== 1) {
            throw new Exception('Invalid time, expected format HH:MM');
        }

        return $this->cron($matches[2], $matches[1], '*', '*', '*');
    }

    /**
     * Marks this task to be dispatched as a detached background process
     * instead of running synchronously within the scheduler process.
     *
     * @param bool $enable
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Scheduling\Task
     */
    public function runInBackground($enable = true)
    {
        if (is_bool($enable) === false) {
            $type = Inspector::type($enable);
            throw new Exception("Expects to be bool, {$type} given");
        }

        $this->background = $enable;

        return $this;
    }

    /**
     * Whether this task should be dispatched as a detached background process
     *
     * @return bool
     */
    public function isBackground()
    {
        return $this->background;
    }

    /**
     * Checks whether the task is due to run now, given the last time it ran
     *
     * @param \DateTime $now
     * @param int|null  $lastRun Unix timestamp of the last execution, or null if it never ran
     * @throws \Inphinit\Exception If no schedule (cron/interval/once/at) was defined
     * @return bool
     */
    public function isDue(\DateTime $now, $lastRun)
    {
        $timestamp = $now->getTimestamp();

        switch ($this->mode) {
            case self::MODE_CRON:
                return $this->matchesCron($now);

            case self::MODE_INTERVAL:
                return $lastRun === null || ($timestamp - $lastRun) >= $this->intervalSeconds;

            case self::MODE_ONCE:
                return $lastRun === null && $timestamp >= $this->onceAt->getTimestamp();

            default:
                throw new Exception('Task has no schedule defined, use cron(), interval(), once() or at()');
        }
    }

    /**
     * Executes the task's callback
     *
     * @return mixed
     */
    public function run()
    {
        $callback = $this->callback;

        return $callback($this);
    }

    private function matchesCron(\DateTime $now)
    {
        list($minute, $hour, $day, $month, $weekday) = $this->cronFields;

        return (
            self::fieldMatches($minute, $now->format('i')) &&
            self::fieldMatches($hour, $now->format('G')) &&
            self::fieldMatches($day, $now->format('j')) &&
            self::fieldMatches($month, $now->format('n')) &&
            self::fieldMatches($weekday, $now->format('w'))
        );
    }

    private static function fieldMatches($allowed, $value)
    {
        // null means "*" (any value is accepted)
        return $allowed === null || in_array($value, $allowed);
    }

    private static function parseCronField($expr, $min, $max, $isWeekday)
    {
        // Parse a single crontab-like field into a list of accepted integers, or null for "any"

        if ($expr === '*') {
            return null;
        }

        if (is_int($expr)) {
            $expr = (string) $expr;
        }

        if (is_string($expr) === false) {
            $type = Inspector::type($expr);
            throw new Exception("Expects to be string, {$type} given", 0, 3);
        }

        $values = array();

        foreach (explode(',', $expr) as $part) {
            if (preg_match('#^(\*|\d+(?:-\d+)?)(?:/(\d+))?$#', $part, $matches) !== 1) {
                throw new Exception('Invalid cron field: ' . $expr, 0, 3);
            }

            $range = $matches[1];
            $step = isset($matches[2]) ? (int) $matches[2] : 1;

            if ($step < 1) {
                throw new Exception('Invalid cron step: ' . $expr, 0, 3);
            }

            if ($range === '*') {
                $start = $min;
                $end = $max;
            } elseif (strpos($range, '-') !== false) {
                list($start, $end) = array_map('intval', explode('-', $range, 2));
            } else {
                $start = $end = (int) $range;
            }

            if ($start < $min || $end > $max || $start > $end) {
                throw new Exception('Cron field out of range: ' . $expr, 0, 3);
            }

            for ($value = $start; $value <= $end; $value += $step) {
                // In crontab, both 0 and 7 mean Sunday for the weekday field
                $normalized = ($isWeekday && $value === 7) ? 0 : $value;
                $values[$normalized] = true;
            }
        }

        return array_keys($values);
    }
}
