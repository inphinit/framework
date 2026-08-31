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
    const MIN_MEMORY_RECOMMENDED = 16777216;
    const MIN_EXEC_RECOMMENDED = 30;
    const MAX_EXEC_RECOMMENDED = 300;
    const MIN_REQUEST_SIZE_RECOMMENDED = 1048576;

    private $iniGetEnabled = false;
    private $development = false;
    private $isHttp = false;

    private $errors = array();
    private $warnings = array();

    private static $shorthands = array(
        'K' => 1,
        'M' => 2,
        'G' => 3,
    );

    public function __construct()
    {
        if (\Inphinit\App::config('environment') === 'development') {
            $this->development = true;
        }

        if (function_exists('ini_get')) {
            $this->iniGetEnabled = true;
        }

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $this->isHttp = true;
        }

        $this->exec();
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
     * Retrieve all ini files
     *
     * @return array
     */
    public static function iniFiles()
    {
        $entries = array();

        if (function_exists('php_ini_scanned_files')) {
            $files = php_ini_scanned_files();

            if ($files !== false) {
                // Remove extra line break in the last file
                $files = trim($files, "\r\n");

                // The file delimiter is always `,\n`
                $entries = array_filter(explode(",\n", $files));
            }
        }

        if (function_exists('php_ini_loaded_file')) {
            $php_ini = php_ini_loaded_file();

            if ($php_ini) {
                $entries[] = $php_ini;
            }
        }

        foreach ($entries as &$entry) {
            $entry = str_replace('\\', '/', $entry);
        }

        return $entries;
    }

    private function checkExecutionTime()
    {
        if ($this->iniGetEnabled) {
            $max_execution_time_entry = ini_get('max_execution_time');
            $max_execution_time = intval($max_execution_time_entry);

            if ($max_execution_time_entry !== strval($max_execution_time)) {
                if ($max_execution_time > 0) {
                    $this->warnings[] = "`max_execution_time={$max_execution_time_entry}` is" .
                                        " interpreted as `max_execution_time={$max_execution_time}`";
                } else {
                    $this->errors[] = "Unexpected value in `max_execution_time={$max_execution_time_entry}`";
                }
            }

            if ($this->isHttp) {
                if ($max_execution_time < 1) {
                    $this->errors[] = 'In a web context, an unlimited `max_execution_time` is unsafe';
                } elseif (
                    $max_execution_time < self::MIN_EXEC_RECOMMENDED ||
                    $max_execution_time > self::MAX_EXEC_RECOMMENDED
                ) {
                    $this->warnings[] = 'In a web context, it is recommended to set `max_execution_time` to 30-300 seconds';
                }
            }
        }
    }

    private function checkMemory()
    {
        if ($this->iniGetEnabled) {
            $memory_limit_entry = ini_get('memory_limit');

            if ($memory_limit_entry === '-1') {
                $memory_limit = -1;
            } else {
                $memory_limit = self::convertSize($memory_limit_entry, '128M');
            }

            if ($memory_limit === false) {
                $this->errors[] = "Invalid value in entry `memory_limit={$memory_limit_entry}`";
            } elseif ($memory_limit === -1) {
                if ($this->isHttp) {
                    $this->errors[] = "Unlimited memory (`memory_limit=-1`) is problematic";
                }
            } elseif ($memory_limit < self::MIN_MEMORY_RECOMMENDED) {
                $this->warnings[] = "`memory_limit={$memory_limit_entry}` may not be enough";
            }
        }
    }

    private function checkPost()
    {
        if ($this->iniGetEnabled && $this->isHttp) {
            $post_max_size_entry = ini_get('post_max_size');
            $upload_max_filesize_entry = ini_get('upload_max_filesize');

            $post_max_size = self::convertSize($post_max_size_entry, '2M');

            if ($post_max_size === false) {
                $this->errors[] = "Invalid value in entry `post_max_size={$post_max_size_entry}`";
            } elseif ($post_max_size < self::MIN_REQUEST_SIZE_RECOMMENDED) {
                $this->warnings[] = "`post_max_size={$post_max_size_entry}` may not be enough";
            }

            if (self::enabled('file_uploads')) {
                $upload_max_filesize = self::convertSize($upload_max_filesize_entry, '8M');

                if ($upload_max_filesize === false) {
                    $this->errors[] = "Invalid value in entry `upload_max_filesize={$upload_max_filesize_entry}`";
                } elseif ($upload_max_filesize < self::MIN_REQUEST_SIZE_RECOMMENDED) {
                    $this->warnings[] = "`upload_max_filesize={$upload_max_filesize_entry}` may not be enough";
                } elseif ($post_max_size !== false && $post_max_size < $upload_max_filesize) {
                    $this->errors[] = "`post_max_size={$post_max_size_entry}` is smaller than " .
                                      "`upload_max_filesize={$upload_max_filesize_entry}`";
                }

                $max_file_uploads_entry = ini_get('max_file_uploads');
                $max_file_uploads = intval($max_file_uploads_entry);

                if ($max_file_uploads_entry !== strval($max_file_uploads)) {
                    if ($max_file_uploads > 0) {
                        $this->warnings[] = "`max_file_uploads={$max_file_uploads_entry}` is" .
                                            " interpreted as `max_file_uploads={$max_file_uploads}`";
                    } else {
                        $this->errors[] = "Unexpected value in `max_file_uploads={$max_file_uploads_entry}`";
                    }
                }

                if ($max_file_uploads < 1) {
                    $this->warnings[] = "`max_file_uploads={$max_file_uploads}` may not be enough";
                }
            } else {
                $this->warnings[] = '`file_uploads=Off` is disabled. If this is not intentional enable it';
            }
        }
    }

    private function checkRandomBytes()
    {
        if (PHP_VERSION_ID < 70000) {
            if (function_exists('mcrypt_create_iv')) {
                $this->warnings[] = '`random_bytes()` unavailable. Using Mcrypt Extension as a fallback';
            } else {
                $this->errors[] = '`random_bytes()` unavailable. Mcrypt Extension is required as a fallback';
            }
        } elseif (function_exists('random_bytes') === false) {
            $this->errors[] = '`random_bytes()` unavailable; check `disable_functions`';
        }
    }

    private function checkStorage()
    {
        $folder = INPHINIT_SYSTEM . '/storage';
        $folder_visible = $this->development ? $folder : './storage';

        if (is_dir($folder) === false) {
            $this->errors[] = "No such directory: `{$folder_visible}`";
        } elseif (is_writable($folder) === false) {
            $this->errors[] = "`{$folder_visible}` directory requires write permissions";
        }
    }

    private function collectErrors()
    {
        if (PHP_VERSION_ID < 80000 && function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc()) {
            $this->errors[] = 'Disable `magic_quotes_gpc`';
        }

        if ($this->iniGetEnabled) {
            if (PHP_VERSION_ID < 70000 && self::enabled('always_populate_raw_post_data')) {
                $this->errors[] = 'Set -1 to `always_populate_raw_post_data`';
            }

            if ($this->development === false && self::enabled('display_errors')) {
                $this->errors[] = 'In production environment, the `display_errors` must be disabled';
            }
        }
    }

    private function collectWarnings()
    {
        if (class_exists('\\Transliterator', false) === false) {
            $this->warnings[] = '(Optional) *Intl* extension is required by `Inphinit\Utility\String` and `Inphinit\Utility\Url`';
        }

        if ($this->iniGetEnabled) {
            if (self::enabled('auto_detect_line_endings')) {
                $this->warnings[] = '`auto_detect_line_endings` is deprecated as of PHP 8.1.0, set to 0';
            }

            if ($this->development) {
                $message = 'While in development mode, it is recommended to disable `%s`';

                if (function_exists('opcache_get_configuration') && self::enabled('opcache.enable')) {
                    $this->warnings[] = sprintf($message, 'opcache.enable');
                }

                if (function_exists('wincache_fcache_meminfo') && self::enabled('wincache.ocenabled')) {
                    $this->warnings[] = sprintf($message, 'wincache.ocenabled');
                }

                if (function_exists('xcache_get') && self::enabled('xcache.cacher')) {
                    $this->warnings[] = sprintf($message, 'xcache.cacher');
                }
            }

            if (self::enabled('expose_php')) {
                $message = 'Set `expose_php=Off` to ensure the PHP version is not exposed through HTTP headers';

                if (PHP_VERSION_ID < 50500) {
                    $message .= ' or PHP easter eggs';
                }

                $this->warnings[] = $message;
            }
        }
    }

    private function exec()
    {
        if ($this->iniGetEnabled === false) {
            $this->warnings[] = 'The `ini_get` function is disabled, so no further checks can be performed';
        }

        if (function_exists('php_ini_loaded_file') === false) {
            $this->warnings[] = '`php_ini_loaded_file()` is disabled, so some checks may be skipped';
        } elseif (php_ini_loaded_file() === false) {
            $this->warnings[] = '`php.ini` is not configured; Ignore this if you are using flags';
        }

        // PHP configurations
        $this->checkExecutionTime();
        $this->checkMemory();
        $this->checkPost();
        $this->checkRandomBytes();
        $this->collectErrors();
        $this->collectWarnings();

        if ($this->development && count($this->errors) > 0) {
            $ini_files = self::iniFiles();

            $message = 'Adjustments should be made in ';

            if ($ini_files) {
                $message .= '`' . implode('`, `', $ini_files) . '`';
            } else {
                $message .= '`php.ini`';
            }

            $this->warnings[] = "{$message} or flags from web server";
        }

        // Other issues
        $this->checkStorage();
    }

    private static function enabled($key)
    {
        return ini_get($key) === '1';
    }

    private static function convertSize($entry, $default)
    {
        if ($entry === '') {
            $entry = $default;
        }

        // According to the PHP FAQ, numeric values are converted to int;
        // therefore, fractional numbers like 0.5M are interpreted as 0.
        if (preg_match('/^(0|[1-9]\d*)(\.\d+|)([KMG]|)$/i', $entry, $matches) !== 1) {
            return false;
        }

        $value = intval($matches[1]);
        $shorthand = strtoupper($matches[3]);

        if ($shorthand !== '') {
            $value *= pow(1024, self::$shorthands[$shorthand]);
        }

        return $value;
    }
}
