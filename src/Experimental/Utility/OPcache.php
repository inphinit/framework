<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Utility;

class OPcache
{
    /**
     * This method should be used in combination with opcache.preload=
     *
     * @param string[] $classes
     * @param string[] &$missing
     * @return bool
     */
    public static function preload(array $classes, &$missing = null)
    {
        $missing = array();

        foreach ($classes as $class) {
            if (self::exists($class) === false) {
                $missing[] = $class;
            }
        }

        return empty($missing);
    }

    private static function exists($name)
    {
        return (class_exists($name, true) || interface_exists($name, false) || trait_exists($name, false));
    }
}
