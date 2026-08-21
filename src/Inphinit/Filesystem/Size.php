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

    private $modes;

    private $bootCOM;
    private $bootCurl;
    private $bootSystem;

    private $comErrorCode;
    private $comErrorMessage;

    private static $osFamily;

    /**
     * Define supported modes
     *
     * @param int $modes
     * @throws \Inphinit\Exception
     */
    public function __construct($modes = 0)
    {
        if (self::$osFamily === null) {
            $os = defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : php_uname('s');

            self::$osFamily = strtolower($os);
        }

        $valid_modes = self::COM | self::CURL | self::SYSTEM;

        if ($modes === 0) {
            $modes = $valid_modes;
        } elseif (is_int($modes) === false || ($modes & ~$valid_modes) !== 0) {
            throw new Exception('Invalid filesize mode(s)');
        }

        if (($modes & self::COM) && (strpos(self::$osFamily, 'win') !== 0 || class_exists('com', false) === false)) {
            $modes &= ~self::COM;
        }

        if (($modes & self::CURL) && function_exists('curl_init') === false) {
            $modes &= ~self::CURL;
        }

        if (($modes & self::SYSTEM) && function_exists('exec') === false) {
            $modes &= ~self::SYSTEM;
        }

        if ($modes === 0) {
            throw new Exception('Selected modes are not supported');
        }

        $this->modes = $modes;
    }

    /**
     * Get file size using defined modes
     * Note: If it is not a file or does not exist, this method will return false.
     *
     * @param string $path Path to the file
     *
     * @throws \Inphinit\Exception Throws an exception with the last error if all modes fail, or if
     *                             the file does not exist.
     *                             Note: Dev mode throws an exception on case-sensitive check failure
     *
     * @return float|int|string Each mode may return a different type of value
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

        if ($this->modes & self::COM) {
            $size = $this->fromCOM($path, $errorMessage, $errorCode);
        }

        if ($size === null && ($this->modes & self::CURL)) {
            $size = $this->fromCurl($path, $errorMessage, $errorCode);
        }

        if ($size === null && ($this->modes & self::SYSTEM)) {
            $size = $this->fromSystem($path, $errorMessage, $errorCode);
        }

        if ($size !== null) {
            return $size;
        }

        throw new Exception($errorMessage, $errorCode);
    }

    public function __destruct()
    {
        $this->bootCOM = null;

        if ($this->bootCurl && PHP_VERSION_ID < 80500) {
            curl_close($this->bootCurl);
        }

        $this->bootCurl = null;
    }

    private function fromCOM($path, &$errorMessage, &$errorCode)
    {
        $boot = $this->bootCOM;

        if ($boot === false) {
            $errorCode = $this->comErrorCode;
            $errorMessage = $this->comErrorMessage;

            return null;
        }

        if ($boot === null) {
            try {
                $boot = new \com('Scripting.FileSystemObject');

                $this->bootCOM = $boot;
            } catch (\Exception $ex) {
                $this->bootCOM = false;

                $errorCode = $ex->getCode();
                $errorMessage = 'COM: ' . $ex->getMessage();

                $this->comErrorCode = $errorCode;
                $this->comErrorMessage = $errorMessage;

                return null;
            }
        }

        try {
            return $boot->GetFile($path)->Size;
        } catch (\Exception $ex) {
            // FileSystemObject failures return messages containing HTML for formatting
            $message = preg_replace('#<br(\s*?)\/?\>#', ' ', $ex->getMessage());
            $message = strip_tags($message);

            $errorCode = $ex->getCode();
            $errorMessage = 'COM: ' . $message;
        }
    }

    private function fromCurl($path, &$errorMessage, &$errorCode)
    {
        if ($this->bootCurl === null) {
            $boot = curl_init();

            curl_setopt($boot, CURLOPT_HEADER, true);
            curl_setopt($boot, CURLOPT_NOBODY, true);
            curl_setopt($boot, CURLOPT_RETURNTRANSFER, true);

            $this->bootCurl = $boot;
        } else {
            $boot = $this->bootCurl;
        }

        // In several tests, it was necessary to encode the URL
        $path = rawurlencode($path);

        curl_setopt($boot, CURLOPT_URL, 'file:///' . $path);

        if (curl_exec($boot) === false) {
            $errorCode = curl_errno($boot);

            // In several tests, error messages were returned encoded
            $errorMessage = 'cURL: ' . rawurldecode(curl_error($boot));

            return null;
        }

        if (defined('CURLINFO_CONTENT_LENGTH_DOWNLOAD_T')) {
            $size = curl_getinfo($boot, CURLINFO_CONTENT_LENGTH_DOWNLOAD_T);
        } else {
            $size = curl_getinfo($boot, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        }

        if ($size >= 0) {
            return $size;
        }

        $errorCode = 0;
        $errorMessage = 'cURL: Unknown size';
    }

    private function fromSystem($path, &$errorMessage, &$errorCode)
    {
        if ($this->bootSystem === null) {
            $os = self::$osFamily;

            if (strpos($os, 'linux') === 0) {
                $command = 'stat -c %%s %s 2>&1';
            } elseif (strpos($os, 'darwin') !== false || strpos($os, 'bsd') !== false) {
                $command = 'stat -f%%z %s 2>&1';
            } elseif (strpos($os, 'win') === 0) {
                $command = '(for %%F in (%s) do @echo "%%~zF") 2>&1';
            } else {
                // Fallback
                $command = 'wc -c < %s 2>&1';
            }

            $this->bootSystem = $command;
        } else {
            $command = $this->bootSystem;
        }

        $command = sprintf($command, escapeshellarg($path));

        $last_line = exec($command, $output, $result_code);

        if ($last_line === false || $result_code !== 0) {
            $errorCode = $result_code;
            $errorMessage = 'System: ' . implode(' ', $output);
        } else {
            $last_line = trim(trim($last_line), '"');

            if (is_numeric($last_line)) {
                return $last_line;
            }

            $errorCode = 0;
            $errorMessage = $last_line ? "System: {$last_line}" : 'System: Unknown error';
        }
    }
}
