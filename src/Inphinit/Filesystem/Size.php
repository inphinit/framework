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
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return float|int|string
     */
    public function get($path)
    {
        if (App::config('development') && File::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)');
        }

        $path = realpath($path);

        if ($path === false) {
            throw new Exception('Invalid path');
        }

        $size = null;

        if (self::$isWin && $this->modes & self::COM) {
            $size = $this->fromCOM($path);
        }

        if ($size === null && $this->modes & self::CURL) {
            $size = $this->fromCurl($path);
        }

        if ($size === null && $this->modes & self::SYSTEM) {
            $size = $this->fromSystem($path);
        }

        if ($size === null) {
            throw new Exception($this->lastError);
        }

        return $size;
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
            $this->lastError = 'CURL: disabled in your server';
        } else {
            $path = rawurlencode($path);

            curl_setopt($boot, CURLOPT_URL, 'file:///' . $path);

            if (curl_exec($boot)) {
                return curl_getinfo($boot, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            } else {
                $this->lastError = 'CURL: ' . curl_error($boot);
            }
        }
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
            $this->lastError = 'COM: `com` class not available on your server or disabled';
        } elseif ($file = $boot->GetFile($path)) {
            return $file->size;
        } else {
            $this->lastError = 'COM: Unable to retrieve the size of ' . $path;
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
            $this->lastError = 'SYSTEM: `shell_exec()` disabled in your server';
        } else {
            $path = sprintf($boot, escapeshellarg($path));

            if ($output = shell_exec($path)) {
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
