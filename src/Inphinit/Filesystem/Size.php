<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
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

    const COM = 1;
    const CURL = 2;
    const SYSTEM = 4;

    /**
     * Define supported modes
     *
     * @param string $modes
     */
    public function __construct($modes = null)
    {
        if (self::$isWin === null) {
            self::$isWin = stripos(PHP_OS, 'WIN') === 0;
        }

        $allModes = self::COM | self::CURL | self::SYSTEM;

        if ($modes === null) {
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
            $size = $this->fromFileSystemObject($path);
        }

        if ($size === null && $this->modes & self::CURL) {
            $size = $this->fromCurl($path);
        }

        if ($size === null && $this->modes & self::SYSTEM) {
            $size = $this->fromSystem($path);
        }

        if ($size === null && $this->lastError) {
            throw new Exception($this->lastError);
        }

        return $size;
    }

    private function fromFileSystemObject($path)
    {
        if (class_exists('com', false)) {
            $obj = new \com('Scripting.FileSystemObject');

            if ($file = $obj->GetFile($path)) {
                return $file->size;
            }

            $this->lastError = 'COM: failed to get size: ' . $path;
        } else {
            $this->lastError = 'COM: Not available on your operating system or disabled';
        }
    }

    private function fromCurl($path)
    {
        if (function_exists('curl_init')) {
            $handle = curl_init('file://' . rawurlencode($path));

            if ($handle !== false) {
                curl_setopt($handle, CURLOPT_HEADER, true);
                curl_setopt($handle, CURLOPT_NOBODY, true);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

                $size = null;

                if (curl_exec($handle)) {
                    $size = curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                } else {
                    $this->lastError = 'CURL: ' . curl_error($handle) . ' from ' . $path;
                }

                curl_close($handle);

                return $size;
            } else {
                $this->lastError = 'CURL: can\'t read ' . $path;
            }
        } else {
            $this->lastError = 'CURL: curl_init is disabled';
        }
    }

    private function fromSystem($path)
    {
        if (function_exists('shell_exec')) {
            $arg = escapeshellarg($path);

            if (self::$isWin) {
                $command = 'for %F in (' . $arg . ') do @echo %~zF';
            } else {
                $command = 'stat -c %s ' . $arg;
            }

            $response = shell_exec($command);

            if ($response) {
                $response = trim($response);
            }

            if (is_numeric($response)) {
                return $response;
            }

            $this->lastError = 'SYSTEM: ' . ($response ? $response : 'Unknown error');
        } else {
            $this->lastError = 'SYSTEM: shell_exec is disabled';
        }
    }
}
