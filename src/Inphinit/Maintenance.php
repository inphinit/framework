<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Maintenance
{
    /**
     * Condition to bypass maintenance mode
     *
     * @param callable $callback
     * @return void
     */
    public static function bypass(callable $callback)
    {
        if (App::config('maintenance') && $callback() === true) {
            App::config('maintenance', false);
        }
    }

    /**
     * Put the application into maintenance mode
     *
     * @return bool
     */
    public static function down()
    {
        return static::enable(true);
    }

    /**
     * Bring the application out of maintenance mode
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
