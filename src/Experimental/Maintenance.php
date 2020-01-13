<?php
/*
 * Inphinit
 *
 * Copyright (c) 2020 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\App;

class Maintenance
{
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
        $config = Config::load('config');

        if ($config->get('maintenance') === $enable) {
            return true;
        }

        $config->set('maintenance', $enable);

        return $config->save();
    }

    /**
     * Up the site only in certain conditions, eg. the site administrator of the IP.
     *
     * @param callable $callback
     * @return void
     */
    public static function ignoreif($callback)
    {
        if (is_callable($callback)) {
            App::on('init', function () use ($callback) {
                if ($callback()) {
                    App::env('maintenance', false);
                }
            });
        } else {
            throw new Exception('Invalid callback');
        }
    }
}
