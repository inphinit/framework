<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Checkup
{
    private $iniPath = '';
    private $iniGet = true;
    private $development = false;
    private $sensitive = false;

    private $errors = array();
    private $warnings = array();

    private $iniConfigs = '`%s`, additional `.ini` files, or directives';
    private $devAdvice = 'While the application is in development mode, it is recommended to disable `%s` in %s';

    public function __construct()
    {
        $this->development = App::config('development');

        $this->sensitive = $this->development;

        if (function_exists('ini_get') === false) {
            $this->warnings[] = '`ini_get` function has been disabled on your server, checking your server settings will be incomplete';
            $this->iniGet = false;
        }

        if (function_exists('php_ini_loaded_file')) {
            $this->iniPath = php_ini_loaded_file();

            if ($this->iniPath) {
                $this->collectErrors();
                $this->collectWarnings();
            } else {
                $this->errors[] = '`php.ini` is not configured on your server';
            }
        } else {
            $this->errors[] = '`php_ini_loaded_file` function has been disabled on your server, it is not possible to check the server settings';
        }
    }

    /**
     * Get errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get warnings
     *
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Show or hide sensitive info
     *
     * @param bool $display
     */
    public function displaySensitive($display)
    {
        $this->sensitive = $display;
    }

    private function collectErrors()
    {
        $directives = $this->getDirectives();

        if (PHP_VERSION_ID < 70400 && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $this->errors[] = 'Disable `magic_quotes_gpc` in ' . $directives;
        }

        if ($this->iniGet && PHP_VERSION_ID < 70000 && ini_get('always_populate_raw_post_data') != -1) {
            $this->errors[] = 'Set -1 to `always_populate_raw_post_data` in ' . $directives;
        }

        if ($this->iniGet && $this->development === false && $this->flag('display_errors')) {
            $this->errors[] = 'Disable `display_errors` in ' . $directives;
        }

        $folder = INPHINIT_SYSTEM . '/storage';

        if (is_dir($folder) && is_writable($folder) === false) {
            $this->errors[] = '`' . ($this->sensitive ? $folder : './storage') . '` directory requires write permissions';
        }

        if (function_exists('mb_detect_encoding') === false) {
            $this->errors[] = '`Inphinit\Utility\Url` class and `Inphinit\Utility\Strings::toAscii` method will not work, to fix it, enable *Multibyte String* in ' . $directives . ' (optional)';
        }

        if (function_exists('iconv') === false) {
            $this->errors[] = '`Inphinit\Utility\String` class will not work, to fix it, enable `iconv` in ' . $directives . ' (optional)';
        }

        if (function_exists('finfo_file') === false) {
            $this->errors[] = '`Inphinit\Filesystem\File::mime` and `Inphinit\Filesystem\File::encoding` methods will not work, to fix it, enable `finfo` in ' . $directives . ' (optional)';
        }
    }

    private function collectWarnings()
    {
        if ($this->development === false && $this->iniGet) {
            $directives = $this->getDirectives();

            if (function_exists('apc_cache_info') && $this->flag('apc.enabled')) {
                $this->warnings[] = sprintf($this->devAdvice, 'apc.enabled', $directives);
            }

            if (function_exists('eaccelerator_get') && $this->flag('eaccelerator.enable')) {
                $this->warnings[] = sprintf($this->devAdvice, 'eaccelerator.enable', $directives);
            }

            if (function_exists('opcache_get_configuration') && $this->flag('opcache.enable')) {
                $this->warnings[] = sprintf($this->devAdvice, 'opcache.enable', $directives);
            }

            if (function_exists('wincache_fcache_meminfo')) {
                if ($this->flag('wincache.fcenabled')) {
                    $this->warnings[] = sprintf($this->devAdvice, 'wincache.fcenabled', $directives);
                }

                if ($this->flag('wincache.ocenabled')) {
                    $this->warnings[] = sprintf($this->devAdvice, 'wincache.ocenabled', $directives);
                }
            }

            if (function_exists('xcache_get') && $this->flag('xcache.cacher')) {
                $this->warnings[] = sprintf($this->devAdvice, 'xcache.cacher', $directives);
            }
        }
    }

    private function flag($key)
    {
        $value = strtolower(ini_get($key));
        return in_array($value, array('on', '1'));
    }

    private function getDirectives()
    {
        if ($this->sensitive && $this->iniPath) {
            return sprintf($this->iniConfigs, $this->iniPath);
        }

        return sprintf($this->iniConfigs, 'php.ini');
    }
}
