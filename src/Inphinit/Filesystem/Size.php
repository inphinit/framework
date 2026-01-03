<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Filesystem;

use Inphinit\App;
use Inphinit\Exception;

class Size
{
    /**
     * Defines the instance to use the COM module to calculate the file size.
     * Note: It can be combined with the other modes, which will serve as alternatives
     *
     * @var int
     */
    const COM = 1;

    /**
     * Defines the instance to use the cURL module to calculate the file size.
     * Note: It can be combined with the other modes, which will serve as alternatives
     *
     * @var int
     */
    const CURL = 2;

    /**
     * Defines the instance to use shell commands to calculate the file size.
     * Note: It can be combined with the other modes, which will serve as alternatives
     *
     * @var int
     */
    const SYSTEM = 4;

    private $error;
    private $modes;
    private static $isWin;

    private static $bootCOM;
    private static $bootCurl;
    private static $bootSystem;

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

        $validModes = self::COM | self::CURL | self::SYSTEM;

        if ($modes === 0) {
            $this->modes = $validModes;
        } elseif (is_int($modes) && ($modes & ~$validModes) === 0) {
            $this->modes = $modes;
        } else {
            throw new Exception('Invalid filesize mode(s)');
        }
    }

    /**
     * Get file size using defined modes
     * Note: If it is not a file or does not exist, this method will return false.
     *
     * @param string $path         Path to the file
     * @throws \Inphinit\Exception If all defined modes fail, an exception will be thrown
     *                             Note: Dev mode throws an exception on case-sensitive check failure
     * @return float|int|string    Each mode may return a different type of value
     */
    public function get($path)
    {
        if (App::config('environment') === 'development' && File::exists($path) === false) {
            throw new Exception($path . ' not found (check case-sensitive)');
        } elseif (is_file($path) === false) {
            throw new Exception($path . ' not found');
        }

        $path = realpath($path);

        $size = null;

        if (self::$isWin && ($this->modes & self::COM)) {
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

        if ($this->error instanceof Exception) {
            throw $this->error;
        }

        $err = $this->error;

        $message = $err->getMessage();
        $message = preg_replace('#<br(\s*?)\/?\>#', ' ', $message);
        $message = strip_tags($message);

        throw new Exception($message, $err->getCode(), 2, $err);
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
            $this->error = new Exception('COM: disabled', 0, 3);
        } else {
            try {
                return $boot->GetFile($path)->Size;
            } catch (\Exception $ee) {
                $this->error = $ee;
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
            $this->error = new Exception('CURL: disabled or not supported by the server', 0, 3);
        } else {
            $path = rawurlencode($path);

            curl_setopt($boot, CURLOPT_URL, 'file:///' . $path);

            if (curl_exec($boot)) {
                return curl_getinfo($boot, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            }

            $this->error = new Exception('CURL: ' . rawurldecode(curl_error($boot)), 0, 3);
        }
    }

    private function fromSystem($path)
    {
        if (self::$bootSystem) {
            $boot = self::$bootSystem;
        } elseif (function_exists('shell_exec')) {
            if (self::$isWin) {
                $boot = 'for %%F in (%s) do @echo "%%~zF"';
            } else {
                $boot = 'stat -c %%s %s';
            }

            self::$bootSystem = $boot;
        } else {
            $boot = false;
        }

        if (!$boot) {
            $this->error = new Exception('SYSTEM: shell_exec function disabled by the server', 0, 3);
        } else {
            $command = sprintf($boot, escapeshellarg($path));
            $output = shell_exec($command);

            if (is_string($output)) {
                $output = trim($output);
                $output = trim($output, '"');

                if (is_numeric($output)) {
                    return $output;
                }

                $this->error = new Exception('SYSTEM: ' . ($output ? $output : 'Unknown error'), 0, 3);
            } else {
                $this->error = new Exception('SYSTEM: Unable to retrieve the size of ' . $path, 0, 3);
            }
        }
    }
}
