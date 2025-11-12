<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Debugging;

use Inphinit\Dom\Document;

class Checkup
{
    private $iniPath = '';
    private $iniGet = true;
    private $development = false;
    private $sensitive = false;

    private $errors = array();
    private $warnings = array();

    const MSG_INI_CONFIGS = '`%s`, additional `.ini` files, or via directives';
    const MSG_DEV_ADVICE = 'While in development mode, it is recommended to disable `%s` in %s';

    public function __construct()
    {
        $this->development = \Inphinit\App::config('development') === true;
        $this->sensitive = $this->development;

        if ($buildDate = self::buildDate()) {
            $current = new \DateTime();
            $diff = $current->diff($buildDate)->y;
            $version = PHP_VERSION;

            if ($diff > 5) {
                $this->errors[] = "Your PHP installation ({$version}) is over {$diff} years old — upgrading to a newer version is strongly recommended for security and performance reasons.";
            } elseif ($diff > 1) {
                $this->errors[] = "Your PHP build ({$version}) hasn't been updated in over {$diff} years — consider applying the latest security patches or upgrading to a newer release.";
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

    /**
     * Retrieve the PHP build date as a DateTime object, or false if unavailable
     *
     * @return \DateTime|bool
     */
    public function buildDate()
    {
        if (function_exists('phpinfo')) {
            ob_start();
            phpinfo(INFO_GENERAL);
            $data = ob_get_clean();

            $handle = new Document(Document::HTML);
            $handle->load($data);

            $elements = $handle->selector()->all('table td:contains(Build Date)+td');
            $dateNode = $elements->item(0);

            if ($dateNode && $value = trim($dateNode->nodeValue)) {
                try {
                    return new \DateTime($value);
                } catch (\Exception $e) {
                    $this->warnings[] = "The PHP release date could not be determined (invalid date string: {$value})";
                }
            } else {
                $this->warnings[] = "The PHP release date could not be determined (missing build date in phpinfo)";
            }
        } else {
            $this->warnings[] = 'The PHP release date could not be determined (phpinfo disabled)';
        }

        return false;
    }

    private function collectErrors()
    {
        $directives = $this->getDirectives();

        if (PHP_VERSION_ID < 80000 && function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) {
            $this->errors[] = 'Disable `magic_quotes_gpc` in ' . $directives;
        }

        if ($this->iniGet && PHP_VERSION_ID < 70000 && ini_get('always_populate_raw_post_data') != -1) {
            $this->errors[] = 'Set -1 to `always_populate_raw_post_data` in ' . $directives;
        }

        if ($this->iniGet && $this->development === false && $this->flag('display_errors')) {
            $this->errors[] = 'In production environment you must disable `display_errors` in ' . $directives;
        }

        $folder = INPHINIT_SYSTEM . '/storage';

        if (is_dir($folder) && is_writable($folder) === false) {
            $this->errors[] = '`' . ($this->sensitive ? $folder : './storage') . '` directory requires write permissions';
        }
    }

    private function collectWarnings()
    {
        if ($this->development && $this->iniGet) {
            $directives = $this->getDirectives();

            if (class_exists('\\Transliterator', false) === false) {
                $this->warnings[] = '(Optional) *Intl* extension is required by `Inphinit\Utility\String` and `Inphinit\Utility\Url`. Enable it in ' . $directives;
            }

            if (function_exists('apc_cache_info') && $this->flag('apc.enabled')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'apc.enabled', $directives);
            }

            if (function_exists('eaccelerator_get') && $this->flag('eaccelerator.enable')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'eaccelerator.enable', $directives);
            }

            if (function_exists('opcache_get_configuration') && $this->flag('opcache.enable')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'opcache.enable', $directives);
            }

            if (function_exists('wincache_fcache_meminfo')) {
                if ($this->flag('wincache.fcenabled')) {
                    $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'wincache.fcenabled', $directives);
                }

                if ($this->flag('wincache.ocenabled')) {
                    $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'wincache.ocenabled', $directives);
                }
            }

            if (function_exists('xcache_get') && $this->flag('xcache.cacher')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'xcache.cacher', $directives);
            }
        }
    }

    private function flag($key)
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
