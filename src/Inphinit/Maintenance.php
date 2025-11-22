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
        return touch(INPHINIT_MAINTENANCE);
    }

    /**
     * Bring the application out of maintenance mode
     *
     * @return bool
     */
    public static function up()
    {
        if (is_file(INPHINIT_MAINTENANCE)) {
            return unlink(INPHINIT_MAINTENANCE);
        }

        return true;
    }
}
