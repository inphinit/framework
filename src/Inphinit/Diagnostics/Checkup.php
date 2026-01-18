<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Diagnostics;

class Checkup
{
    const SUPPORT_ACTIVE = 80400;
    const SUPPORT_SECURITY_ONLY = 80200;

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
        if (\Inphinit\App::config('environment') === 'development') {
            $this->development = true;
            $this->sensitive = true;
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
     * Excute the checkup
     */
    public function exec()
    {
        $this->errors = array();
        $this->warnings = array();

        if ($this->sensitive) {
            $version = PHP_VERSION;

            if (PHP_VERSION_ID < self::SUPPORT_SECURITY_ONLY) {
                $this->errors[] = "PHP {$version} is no longer supported. " .
                                  'Updating as soon as possible is strongly recommended';
            } elseif (PHP_VERSION_ID < self::SUPPORT_ACTIVE) {
                $this->warnings[] = "PHP {$version} continues to receive security updates; " .
                                    'however, upgrading is recommended to benefit from further improvements';
            }
        }

        if (function_exists('ini_get') === false) {
            $this->warnings[] = 'The `ini_get` function is disabled, so no further checks can be performed';
            $this->iniGet = false;
        }

        if (function_exists('php_ini_loaded_file')) {
            $this->iniPath = php_ini_loaded_file();

            if ($this->iniPath) {
                $this->checkMemory();
                $this->checkPost();
                $this->checkRandomBytes();
                $this->collectErrors();
                $this->collectWarnings();
            } else {
                $this->warnings[] = '`php.ini` is not configured or could not be located on this server';
            }
        } else {
            $this->warnings[] = 'The `php_ini_loaded_file()` is disabled, so no further checks can be performed';
        }
    }

    private function checkMemory()
    {
        $directives = $this->getDirectives();

        $memory_limit_entry = ini_get('memory_limit');

        if ($memory_limit_entry === '-1') {
            $memory_limit = -1;
        } else {
            $memory_limit = self::convertSize($memory_limit_entry, '128M');
        }

        if ($memory_limit === false) {
            $this->errors[] = "Invalid value in entry `memory_limit={$memory_limit_entry}`. " .
                              "Adjustment in {$directives}";
        } elseif ($memory_limit === -1) {
            $this->errors[] = "Unlimited memory (`memory_limit=-1`) is problematic. Adjustment in {$directives}";
        } elseif ($memory_limit < 16 * 1024 * 1024) {
            $this->warnings[] = "`memory_limit={$memory_limit_entry}` may not be enough. " .
                                "Adjustment in {$directives}";
        }
    }

    private function checkPost()
    {
        $directives = $this->getDirectives();

        $post_max_size_entry = ini_get('post_max_size');
        $upload_max_filesize_entry = ini_get('upload_max_filesize');

        if ($post_max_size_entry === '') {
            $post_max_size_entry = '2M';
        }

        if ($upload_max_filesize_entry === '') {
            $upload_max_filesize_entry = '8M';
        }

        $min_size = 1024 * 1024;
        $post_max_size = self::convertSize($post_max_size_entry, '8M');

        if ($post_max_size === false) {
            $this->errors[] = "Invalid value in entry `post_max_size={$post_max_size_entry}`. " .
                              "Adjustment in {$directives}";
        } elseif ($post_max_size < $min_size) {
            $this->errors[] = "`post_max_size={$post_max_size_entry}` is very small. " .
                              "Adjustment in {$directives}";
        }

        if (self::isEnabled('file_uploads')) {
            $upload_max_filesize = self::convertSize($upload_max_filesize_entry, '2M');

            if ($upload_max_filesize === false) {
                $this->errors[] = "Invalid value in entry `upload_max_filesize={$upload_max_filesize_entry}`. " .
                                  "Adjustment in {$directives}";
            }

            if ($post_max_size < $upload_max_filesize) {
                $this->errors[] = "`post_max_size={$post_max_size_entry}` is smaller than " .
                                  "`upload_max_filesize={$upload_max_filesize_entry}`. " .
                                  "Adjustment in {$directives}";
            } elseif ($upload_max_filesize < $min_size) {
                $this->errors[] = "`upload_max_filesize={$upload_max_filesize_entry}` is very small. " .
                                  "Adjustment in {$directives}";
            }
        } elseif (PHP_SAPI !== 'cli') {
            $this->warnings[] = 'File uploads are disabled. If this is not intentional' .
                                ', enable `file_uploads=On` in ' . $directives;
        }
    }

    private function checkRandomBytes()
    {
        $directives = $this->getDirectives();

        if (function_exists('random_bytes') === false) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $this->warnings[] = '`random_bytes()` unavailable. Using OpenSSL as a fallback';
            } elseif (PHP_VERSION_ID < 70000) {
                $this->errors[] = '`random_bytes()` polyfill is required or enable OpenSSL extension in ' . $directives;
            } else {
                $this->errors[] = '`random_bytes()` function or OpenSSL extension is required; ' .
                                  'check disable_functions in ' . $directives;
            }
        }
    }

    private function collectErrors()
    {
        $directives = $this->getDirectives();

        if (PHP_VERSION_ID < 80000 && function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) {
            $this->errors[] = 'Disable `magic_quotes_gpc` in ' . $directives;
        }

        if ($this->iniGet) {
            if (PHP_VERSION_ID < 70000 && self::isEnabled('always_populate_raw_post_data')) {
                $this->errors[] = 'Set -1 to `always_populate_raw_post_data` in ' . $directives;
            }

            if ($this->development === false && self::isEnabled('display_errors')) {
                $this->errors[] = 'In production environment, the `display_errors` must be disabled in ' . $directives;
            }

            if (PHP_SAPI !== 'cli') {
                $max_execution_time = ini_get('max_execution_time');

                if ($max_execution_time < 1) {
                    $this->errors[] = 'In a web context, an unlimited `max_execution_time` is unsafe. ' .
                                      'Adjustment in ' . $directives;
                } elseif ($max_execution_time > 300) {
                    $this->errors[] = 'In a web context, `max_execution_time` should typically be limited to 30–300 ' .
                                      'seconds. Adjustment in ' . $directives;
                }
            }
        }

        $folder = INPHINIT_SYSTEM . '/storage';

        if (is_dir($folder) && is_writable($folder) === false) {
            $folder = $this->sensitive ? $folder : './storage';

            $this->errors[] = "`{$folder}` directory requires write permissions";
        }
    }

    private function collectWarnings()
    {
        $directives = $this->getDirectives();

        if (class_exists('\\Transliterator', false) === false) {
            $this->warnings[] = '(Optional) *Intl* extension is required by `Inphinit\Utility\String`' .
                                ' and `Inphinit\Utility\Url`.  Enable it in ' . $directives;
        }

        if ($this->iniGet && self::isEnabled('auto_detect_line_endings')) {
            $this->warnings[] = '`auto_detect_line_endings` is deprecated as of PHP 8.1.0,' .
                                ' set 0 or remove in ' . $directives;
        }

        if ($this->development && $this->iniGet) {
            if (function_exists('opcache_get_configuration') && self::isEnabled('opcache.enable')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'opcache.enable', $directives);
            }

            if (function_exists('wincache_fcache_meminfo')) {
                if (self::isEnabled('wincache.ocenabled')) {
                    $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'wincache.ocenabled', $directives);
                }
            }

            if (function_exists('xcache_get') && self::isEnabled('xcache.cacher')) {
                $this->warnings[] = sprintf(self::MSG_DEV_ADVICE, 'xcache.cacher', $directives);
            }
        }

        if ($this->iniGet && self::isEnabled('expose_php')) {
            $message = 'Set `expose_php=Off` to ensure the PHP version is not exposed through HTTP headers';

            if (PHP_VERSION_ID < 505000) {
                $message .= ' or PHP easter eggs';
            }

            $this->warnings[] = $message. ' in ' . $directives;
        }
    }

    private static function isEnabled($key)
    {
        $value = ini_get($key);
        return $value ? in_array(strtolower($value), array('on', '1', 'yes', 'true')) : false;
    }

    private function getDirectives()
    {
        if ($this->sensitive && $this->iniPath) {
            return sprintf(self::MSG_INI_CONFIGS, $this->iniPath);
        }

        return sprintf(self::MSG_INI_CONFIGS, 'php.ini');
    }

    private static function convertSize($entry, $default)
    {
        if ($entry === '') {
            $entry = $default;
        }

        if (preg_match('/^(\d+?)([KMG]|)$/i', $entry, $matches) !== 1) {
            return false;
        }

        $value = intval($matches[1]);
        $shorthand = $matches[2];

        if ($shorthand) {
            $value *= pow(1024, stripos('_KMG', $shorthand));
        }

        return $value;
    }
}
