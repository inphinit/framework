<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Diagnostics;

use Inphinit\Dom\Document;

class Checkup
{
    const AGE_CHECK = 1;
    const AGE_LEGACY = 5;

    private $iniPath = '';
    private $iniGet = true;
    private $development = false;
    private $sensitive = false;

    private $errors = array();
    private $warnings = array();

    private static $buildAge;

    const MSG_INI_CONFIGS = '`%s`, additional `.ini` files, or via directives';
    const MSG_DEV_ADVICE = 'While in development mode, it is recommended to disable `%s` in %s';

    public function __construct()
    {
        $this->development = \Inphinit\App::config('development') === true;
        $this->sensitive = $this->development;

        if ($buildAge = self::getBuildAge()) {
            $version = PHP_VERSION;

            if ($buildAge > self::AGE_LEGACY) {
                $this->errors[] = "Your PHP installation ({$version}) is over {$buildAge} years old — " .
                                  "upgrading to a newer version is strongly recommended for security and performance reasons";
            } elseif ($buildAge > self::AGE_CHECK) {
                $this->errors[] = "Your PHP build ({$version}) hasn't been updated in over {$buildAge} years — " .
                                  "consider applying the latest security patches or upgrading to a newer release";
            }
        }

        if (function_exists('ini_get') === false) {
            $this->warnings[] = 'The `ini_get` function is disabled on this server; configuration checks will be incomplete';
            $this->iniGet = false;
        }

        if (function_exists('php_ini_loaded_file')) {
            $this->iniPath = php_ini_loaded_file();

            if ($this->iniPath) {
                $this->collectErrors();
                $this->collectWarnings();
            } else {
                $this->warnings[] = '`php.ini` is not configured or could not be located on this server';
            }
        } else {
            $this->warnings[] = 'The `php_ini_loaded_file` function is disabled, preventing server configuration checks';
        }
    }

    /**
     * Retrieve all detected configuration errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Retrieve all detected configuration warnings
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Enable or disable the display of sensitive information in paths and file names
     *
     * @param bool $display
     */
    public function setDisplaySensitive($display)
    {
        $this->sensitive = $display;
    }

    private static function getBuildAge()
    {
        if (self::$buildAge !== null) {
            return self::$buildAge;
        }

        $date = null;

        if (defined('PHP_BUILD_DATE')) {
            $date = PHP_BUILD_DATE;
        } elseif (function_exists('phpinfo')) {
            ob_start();
            phpinfo(INFO_GENERAL);

            $handle = new Document(Document::HTML);
            $handle->load(ob_get_clean());

            $node = $handle->selector()->first('td:contains(Build Date)+td');

            if ($node && $value = trim($node->nodeValue)) {
                $date = $value;
            } else {
                $this->warnings[] = "The PHP release date could not be determined (missing build date in phpinfo)";
            }

            $handle = null;
        }

        $age = false;

        if ($date) {
            try {
                $current = new \DateTime();
                $build = new \DateTime($date);
                $age = $current->diff($build)->y;
            } catch (\Exception $e) {
                $this->warnings[] = "The PHP release date could not be determined (invalid date: {$date})";
            }
        } else {
            $this->warnings[] = 'The PHP release date could not be determined';
        }

        self::$buildAge = $age;

        return $age;
    }

    private function collectErrors()
    {
        $directives = $this->getDirectives();

        if (PHP_VERSION_ID < 80000 && function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) {
            $this->errors[] = 'Disable `magic_quotes_gpc` in ' . $directives;
        }

        if ($this->iniGet && PHP_VERSION_ID < 70000 && self::isEnabled('always_populate_raw_post_data')) {
            $this->errors[] = 'Set -1 to `always_populate_raw_post_data` in ' . $directives;
        }

        if ($this->iniGet && $this->development === false && self::isEnabled('display_errors')) {
            $this->errors[] = 'In production environment you must disable `display_errors` in ' . $directives;
        }

        $folder = INPHINIT_SYSTEM . '/storage';

        if (is_dir($folder) && is_writable($folder) === false) {
            $this->errors[] = '`' . ($this->sensitive ? $folder : './storage') .
                              '` directory requires write permissions';
        }
    }

    private function collectWarnings()
    {
        $directives = $this->getDirectives();

        if (class_exists('\\Transliterator', false) === false) {
            $this->warnings[] = '(Optional) *Intl* extension is required by `Inphinit\Utility\String`' .
                                ' and `Inphinit\Utility\Url`.  Enable it in ' . $directives;
        }
        if ($this->iniGet && PHP_VERSION_ID < 70000 && self::isEnabled('auto_detect_line_endings')) {
            $this->warnings[] = '`auto_detect_line_endings` is deprecated, set 0 or remove in ' . $directives;
        }

        if ($this->development && $this->iniGet) {
            if (function_exists('opcache_get_configuration') && self::isEnabled('opcache.enable')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'opcache.enable', $directives);
            }

            if (function_exists('apc_cache_info') && self::isEnabled('apc.enabled')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'apc.enabled', $directives);
            }

            if (function_exists('eaccelerator_get') && self::isEnabled('eaccelerator.enable')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'eaccelerator.enable', $directives);
            }

            if (function_exists('wincache_fcache_meminfo')) {
                if (self::isEnabled('wincache.fcenabled')) {
                    $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'wincache.fcenabled', $directives);
                }

                if (self::isEnabled('wincache.ocenabled')) {
                    $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'wincache.ocenabled', $directives);
                }
            }

            if (function_exists('xcache_get') && self::isEnabled('xcache.cacher')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'xcache.cacher', $directives);
            }
        }
    }

    private static function isEnabled($key)
    {
        $value = ini_get($key);
        return $value ? in_array(strtolower($value), array('on', '1', 'yes', 'no')) : false;
    }

    private function getDirectives()
    {
        if ($this->sensitive && $this->iniPath) {
            return sprintf(self::MSG_INI_CONFIGS, $this->iniPath);
        }

        return sprintf(self::MSG_INI_CONFIGS, 'php.ini');
    }
}
