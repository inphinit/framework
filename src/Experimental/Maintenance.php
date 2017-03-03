<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 *
 * @since 0.0.1
 */

namespace Inphinit\Experimental;

use Inphinit\App;
use Inphinit\Storage;

class Maintenance
{
    /**
     * Down site to maintenance mode
     *
     * @return bool
     */
    public static function down()
    {
        return self::enable(true);
    }

    /**
     * Up site
     *
     * @return bool
     */
    public static function up()
    {
        return self::enable(false);
    }

    /**
     * Enable/disable maintenance mode
     *
     * @param bool $enable
     * @return bool
     */
    protected static function enable($enable)
    {
        $data = include INPHINIT_PATH . 'application/Config/config.php';

        if ($data['maintenance'] === $enable) {
            return true;
        }

        $data['maintenance'] = $enable;

        $wd = preg_replace('#,(\s+|)\)#', '$1)', var_export($data, true));

        $path = Storage::temp('<?php' . EOL . 'return ' . $wd . ';' . EOL);

        $response = copy($path, INPHINIT_PATH . 'application/Config/config.php');

        unlink($path);

        return $response;
    }

    /**
     * Up the site only in certain conditions, eg. the site administrator of the IP.
     *
     * @param callable $callback
     * @return void
     */
    public static function ignoreif($callback)
    {
        if (is_callable($callback) === false) {
            throw new Exception('Invalid callback');
        }

        App::on('init', function () use ($callback) {
            if ($callback()) {
                App::env('maintenance', false);
            }
        });
    }
}
