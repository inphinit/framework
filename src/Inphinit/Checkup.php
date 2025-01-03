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
    private $iniPath;
    private $iniGet = true;
    private $development = false;

    private $errors = array();
    private $warnings = array();

    private static $devAdvice = 'While your application is in development mode, it is recommended to disable `%s` in `%s`';

    public function __construct()
    {
        $this->development = App::config('development');

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
                $this->errors[] = 'php.ini is not configured on your server';
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

    private function collectErrors()
    {
        if (version_compare(PHP_VERSION, '7.4.0', '<') && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $this->errors[] = 'Disable `magic_quotes_gpc` in `' . $this->iniPath . '`';
        }

        if (version_compare(PHP_VERSION, '7.0.0', '<') && ini_get('always_populate_raw_post_data') != -1) {
            $this->errors[] = 'Set -1 to `always_populate_raw_post_data` in `' . $this->iniPath . '`';
        }

        $folder = INPHINIT_SYSTEM . '/storage';

        if (is_dir($folder) && is_writable($folder) === false) {
            $this->errors[] = 'Folder ' . $folder . ' requires write permissions, use chmod';
        }

        if (function_exists('mb_detect_encoding') === false) {
            $this->errors[] = '`Inphinit\Uri` class `Inphinit\Utility\Strings::toAscii` method will not work, to fix it, enable *Multibyte String* in `' . $this->iniPath . '` (optional)';
        }

        if (function_exists('iconv') === false) {
            $this->errors[] = '`Inphinit\Utility\String` class will not work, to fix it, enable `iconv` in `' . $this->iniPath . '` (optional)';
        }

        if (function_exists('finfo_file') === false) {
            $this->errors[] = '`Inphinit\Filesystem\File::mime` and `Inphinit\Filesystem\File::encoding` methods will not work, to fix it, enable `finfo` in `' . $this->iniPath . '` (optional)';
        }
    }

    private function collectWarnings()
    {
        if (!$this->development && $this->iniGet) {
            if (function_exists('xcache_get') && $this->flag('xcache.cacher')) {
                $this->warnings[] = sprintf($this->devAdvice, 'xcache.cacher', $this->iniPath);
            }

            if (function_exists('opcache_get_status') && $this->flag('opcache.enable')) {
                $this->warnings[] = sprintf($this->devAdvice, 'opcache.enable', $this->iniPath);
            }

            if (function_exists('wincache_ocache_meminfo') && $this->flag('wincache.ocenabled')) {
                $this->warnings[] = sprintf($this->devAdvice, 'wincache.ocenabled', $this->iniPath);
            }

            if (function_exists('apc_compile_file') && $this->flag('apc.enabled')) {
                $this->warnings[] = sprintf($this->devAdvice, 'apc.ocenabled', $this->iniPath);
            }

            if (function_exists('eaccelerator_get') && $this->flag('eaccelerator.enable')) {
                $this->warnings[] = sprintf($this->devAdvice, 'eaccelerator.ocenabled', $this->iniPath);
            }
        }
    }

    private function flag($key)
    {
        $value = strtolower(ini_get($key));
        return in_array($value, array('on', '1'));
    }
}
