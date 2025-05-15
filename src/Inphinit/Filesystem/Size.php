<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Filesystem;

use Inphinit\App;
use Inphinit\Exception;

class Size
{
    private $modes;
    private $lastError;
    private static $isWin;

    private static $bootCOM;
    private static $bootCurl;
    private static $bootSystem;

    const COM = 1;
    const CURL = 2;
    const SYSTEM = 4;

    /**
     * Define supported modes
     *
     * @param int $modes
     * @throws \Inphinit\Exception
     */
    public function __construct($modes = 0)
    {
        if (self::$isWin === null) {
            self::$isWin = stripos(PHP_OS, 'WIN') === 0;
        }

        $allModes = self::COM | self::CURL | self::SYSTEM;

        if ($modes === 0) {
            $this->modes = $allModes;
        } elseif (is_int($modes) && $allModes & $modes) {
            $this->modes = $modes;
        } else {
            throw new Exception('Invalid mode');
        }
    }

    /**
     * Get file size using defined modes
     * Note: If it is not a file or does not exist, this method will return false.
     *
     * @param string $path Path to the file
     * @throws \Inphinit\Exception If all defined modes fail, an exception will be thrown
     *                             Note: Dev mode throws an exception on case-sensitive check failure
     * @return float|int|string|false Each mode may return a different type of value
     */
    public function get($path)
    {
        $path = realpath($path);

        if ($path === false || is_file($path) === false) {
            return false;
        } elseif (App::config('development') && File::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)');
        }

        $size = null;

        if ($this->modes & self::COM) {
            $size = $this->fromCOM($path);
        }

        if ($size === null && $this->modes & self::CURL) {
            $size = $this->fromCurl($path);
        }

        if ($size === null && $this->modes & self::SYSTEM) {
            $size = $this->fromSystem($path);
        }

        if ($size !== null) {
            return $size;
        }

        if (is_string($this->lastError)) {
            throw new Exception($this->lastError);
        }

        $message = $this->lastError->getMessage();
        $message = preg_replace('#<br(\s+)?\/?>#', ' ', $message);
        $message = strip_tags($message);

        throw new Exception($message, $this->lastError->getCode());
    }

    private function fromCOM($path)
    {
        if (self::$bootCOM) {
            $boot = self::$bootCOM;
        } elseif (class_exists('com', false)) {
            $boot = new \com('Scripting.FileSystemObject');
            self::$bootCOM = $boot;
        } else {
            $boot = false;
        }

        if (!$boot) {
            $this->lastError = 'COM: disabled or not supported by the server';
        } else {
            try {
                $file = $boot->GetFile($path);
                return $file->size;
            } catch (\Exception $ee) {
                $this->lastError = $ee;
            }
        }
    }

    private function fromCurl($path)
    {
        if (self::$bootCurl) {
            $boot = self::$bootCurl;
        } elseif (function_exists('curl_init')) {
            $boot = curl_init();
            curl_setopt($boot, CURLOPT_HEADER, true);
            curl_setopt($boot, CURLOPT_NOBODY, true);
            curl_setopt($boot, CURLOPT_RETURNTRANSFER, true);

            self::$bootCurl = $boot;
        } else {
            $boot = false;
        }

        if (!$boot) {
            $this->lastError = 'CURL: disabled or not supported by the server';
        } else {
            $path = rawurlencode($path);

            curl_setopt($boot, CURLOPT_URL, 'file:///' . $path);

            if (curl_exec($boot)) {
                return curl_getinfo($boot, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            } else {
                $this->lastError = 'CURL: ' . rawurldecode(curl_error($boot));
            }
        }
    }

    private function fromSystem($path)
    {
        if (self::$bootSystem) {
            $boot = self::$bootSystem;
        } elseif (function_exists('shell_exec')) {
            if (self::$isWin) {
                $boot = 'for %%F in (%s) do @echo %%~zF';
            } else {
                $boot = 'stat -c %%s %s';
            }

            self::$bootSystem = $boot;
        } else {
            $boot = false;
        }

        if (!$boot) {
            $this->lastError = 'SYSTEM: shell_exec function disabled by the server';
        } else {
            $command = sprintf($boot, escapeshellarg($path));

            if ($output = shell_exec($command)) {
                $output = trim($output);

                if (is_numeric($output)) {
                    return $output;
                }

                $this->lastError = 'SYSTEM: ' . ($output ? $output : 'Unknown error');
            } else {
                $this->lastError = 'SYSTEM: Unable to retrieve the size of ' . $path;
            }
        }
    }
}
