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
    private static $configs = array();

    /**
     * If a maintenance event returns `false` (stop propagation)
     * this method will return `true`; otherwise, it will return `false`.
     *
     * @return bool
     */
    public static function bypassed()
    {
        return Event::trigger('maintenance') === Event::TRIGGER_STOPPED;
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
        $config = self::config('app');

        if ($config->maintenance === $enable) {
            return true;
        }

        $config->maintenance = $enable;

        return $config->commit();
    }

    private static function config($config)
    {
        if (isset(self::$configs[$config]) === false) {
            self::$configs[$config] = new Config($config);
        }

        return self::$configs[$config];
    }
}
