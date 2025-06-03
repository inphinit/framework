<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Filesystem\File;
use Inphinit\Http\Response;
use Inphinit\Exception;

class FileResponse
{
    /** Prefer X-Accel-Redirect header for file delivery (e.g. nginx) */
    const ACCEL = 1;

    /** Prefer X-Sendfile header for file delivery (e.g. Apache, Lighttpd) */
    const SENDFILE = 2;

    /** Fallback: deliver file using PHP (less efficient, not recommended) */
    const FALLBACK = 4;

    private $filename;
    private $modes;
    private $source;

    /**
     * Initialize the response with a file path and optional download name
     *
     * @param string $source   Absolute file path.
     * @param string $filename Optional. Set download name (defaults to basename of $source)
     * @param int    $modes    Opcional. Set file delivery modes using bitwise flags (ACCEL, SENDFILE, FALLBACK)
     */
    public function __construct($source, $filename = '', $modes = 0)
    {
        if (preg_match('#[\r\n]#', $source)) {
            throw new Exception('$source may not contain more than a single header, new line detected', 0, 3);
        }

        if ($filename && preg_match('#[\r\n]#', $filename)) {
            throw new Exception('$filename may not contain more than a single header, new line detected', 0, 3);
        }

        $this->filename = $filename ? $filename : basename($source);
        $this->source = $source;

        $validModes = self::ACCEL | self::SENDFILE | self::FALLBACK;

        if ($modes === 0) {
            $modes = self::ACCEL | self::SENDFILE;
        } elseif (!is_int($modes) || ($modes & ~$validModes) !== 0) {
            throw new Exception('Invalid delivery mode(s)');
        }

        $this->modes = $modes;
    }

    /**
     * Check if a specific delivery mode is supported by the server environment
     * Note: In the built-in web server, all modes will return true, allowing you to use the simulator to deliver the file.
     *
     * @param int $mode One of the mode constants (ACCEL or SENDFILE)
     * @return bool
     */
    public static function available($mode)
    {
        if (PHP_SAPI === 'cli-server') {
            return true;
        }

        if ($mode === self::ACCEL) {
            $env = 'MOD_X_ACCEL_REDIRECT_ENABLED';
        } elseif ($mode === self::SENDFILE) {
            $env = 'MOD_X_SENDFILE_ENABLED';
        } else {
            return false;
        }

        if (isset($_SERVER[$env])) {
            $value = strtolower($_SERVER[$env]);
            return in_array($value, array('1', 'on', 'true', 'yes'));
        }

        return false;
    }

    /**
     * Dispatch the file using the preferred available delivery method
     *
     * @param bool $overwrite      Optional. Overwrite all possible related headers
     * @throws \Inphinit\Exception If headers are already sent or no supported mode is available
     * @return void
     */
    public function send($overwrite = false)
    {
        if (headers_sent()) {
            throw new Exception('Cannot dispatch file, headers have already been sent');
        }

        $this->checkDispatched($overwrite);

        $modes = $this->modes;
        $fallback = ($modes & self::FALLBACK) !== 0;
        $source = $this->source;
        $header = null;

        if (($modes & self::ACCEL) && $this->available(self::ACCEL)) {
            $header = 'X-Accel-Redirect';
        } elseif (($modes & self::SENDFILE) && $this->available(self::SENDFILE)) {
            $header = 'X-Sendfile';
        }

        if ($header === null && !$fallback) {
            throw new Exception('No supported modes. Check server configuration or enable FALLBACK mode');
        }

        // Send headers for file download
        Response::download($this->filename);

        if ($header) {
            Response::header($header, $source);
        } elseif ($fallback) {
            File::output($source);
        }
    }

    private function checkDispatched($overwrite)
    {
        $accelHeader = 'X-Accel-Redirect';
        $sendHeader = 'X-Sendfile';

        if ($overwrite) {
            Response::header($accelHeader, null);
            Response::header($sendHeader, null);
        } else {
            foreach (headers_list() as $header) {
                if (stripos($header, $accelHeader) === 0 || stripos($header, $sendHeader) === 0) {
                    throw new Exception('Conflicting file delivery headers detected', 0, 3);
                }
            }
        }
    }
}
