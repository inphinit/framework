<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Maintenance
{
    /**
     * Down site to maintenance mode
     *
     * @param callable $callback
     * @return void
     */
    public static function ignore(callable $callback)
    {
        if (App::config('maintenance') && $callback() === true) {
            App::config('maintenance', false);
        }
    }

    /**
     * Down site to maintenance mode
     *
     * @return bool
     */
    public static function down()
    {
        return static::enable(true);
    }

    /**
     * Up site
     *
     * @return bool
     */
    public static function up()
    {
        return static::enable(false);
    }

    /**
     * Enable/disable maintenance mode
     *
     * @param bool $enable
     * @return bool
     */
    protected static function enable($enable)
    {
        $config = new Config('app');

        if ($config->maintenance === $enable) {
            return true;
        }

        $config->maintenance = $enable;

        return $config->commit();
    }
}
