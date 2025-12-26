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
use Inphinit\Exception;

class Checkup
{
    const AGE_CHECK = 1;
    const AGE_LEGACY = 2;

    private $iniPath = '';
    private $iniGet = true;
    private $development = false;
    private $sensitive = false;

    private $errors = array();
    private $warnings = array();

    private $buildAge;
    private static $buildDate;

    const MSG_INI_CONFIGS = '`%s`, additional `.ini` files, or via directives';
    const MSG_DEV_ADVICE = 'While in development mode, it is recommended to disable `%s` in %s';

    const CACHE_PHP_BUILD_DATE = '.PHP_BUILD_DATE';

    public function __construct()
    {
        if (\Inphinit\App::config('environment') === 'development') {
            $this->development = true;
            $this->sensitive = true;
        }

        if ($this->sensitive && ($buildAge = $this->getBuildAge())) {
            $version = PHP_VERSION;

            if ($buildAge > self::AGE_LEGACY) {
                $this->errors[] = "PHP{$version} is more than {$buildAge} years old — upgrading to a " .
                                  "newer version is strongly recommended for security and performance reasons";
            } elseif ($buildAge > self::AGE_CHECK) {
                $this->warnings[] = "PHP{$version} has not received updates for over {$buildAge} years — " .
                                    "consider applying the latest security patches or upgrading to a newer release";
            }
        }

        if (function_exists('ini_get') === false) {
            $this->warnings[] = 'The `ini_get` function is disabled, so no further checks can be performed';
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
            $this->warnings[] = 'The `php_ini_loaded_file()` is disabled, so no further checks can be performed';
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
     * Get PHP build date (backward compatibility support)
     *
     * @throws \Inphinit\Exception
     * @return string
     */
    public static function getBuildDate()
    {
        if (defined('PHP_BUILD_DATE')) {
            return PHP_BUILD_DATE;
        } elseif (self::$buildDate === null) {
            $cache = INPHINIT_SYSTEM . '/storage/' . self::CACHE_PHP_BUILD_DATE;

            if (is_file($cache)) {
                self::$buildDate = file_get_contents($cache);
            } elseif (function_exists('phpinfo')) {
                ob_start();

                phpinfo(INFO_GENERAL);

                $handle = new Document(Document::HTML);
                $handle->load(ob_get_clean());

                $node = $handle->selector()->first('td:contains(Build Date)+td');

                if ($node && $value = trim($node->textContent)) {
                    self::$buildDate = $value;
                    file_put_contents($cache, $value);
                } else {
                    throw new Exception('The PHP release date could not be determined (missing build date in phpinfo())');
                }
            } else {
                throw new Exception('The PHP release date could not be determined (phpinfo() is disabled)');
            }
        }

        return self::$buildDate;
    }

    private function getBuildAge()
    {
        if ($this->buildAge !== null) {
            return $this->buildAge;
        }

        $date = self::getBuildDate();

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

        $this->buildAge = $age;

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
            $this->errors[] = 'In production environment, the `display_errors` must be disabled in ' . $directives;
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
            $this->warnings[] = '`auto_detect_line_endings` is deprecated as of PHP 8.1.0, set 0 or remove in ' . $directives;
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
    }

    private static function isEnabled($key)
    {
        $value = ini_get($key);
        return $value ? in_array(strtolower($value), array('on', '1', 'yes')) : false;
    }

    private function getDirectives()
    {
        if ($this->sensitive && $this->iniPath) {
            return sprintf(self::MSG_INI_CONFIGS, $this->iniPath);
        }

        return sprintf(self::MSG_INI_CONFIGS, 'php.ini');
    }
}
