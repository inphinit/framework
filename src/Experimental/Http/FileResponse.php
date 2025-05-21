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
    const ACCEL    = 1;
    const SENDFILE = 2;
    const FALLBACK = 4;

    private $source;
    private $filename;
    private $modes;

    public function __construct($source, $filename = '', $modes = 0)
    {
        $allModes = self::ACCEL | self::SENDFILE | self::FALLBACK;

        $this->modes = $modes;
        $this->source = $source;

        if (!$filename) {
            $this->filename = basename($source);
        }

        if ($modes === 0) {
            $this->modes = self::ACCEL | self::SENDFILE;
        } elseif (is_int($modes) && $allModes & $modes) {
            $this->modes = $modes;
        } else {
            throw new Exception('Invalid mode');
        }
    }

    public function available($mode)
    {
        if ($mode & self::ACCEL) {
            return getenv('MOD_ACCEL_ENABLED') === '1';
        } elseif ($mode & self::SENDFILE) {
            return getenv('MOD_X_SENDFILE_ENABLED') === '1';
        }

        return false;
    }

    public function send()
    {
        if (headers_sent()) {
            throw new Exception('Cannot modify header information - headers already sent');
        }

        $fallback = ($this->modes & self::FALLBACK) !== 0;
        $header = null;
        $mode = $this->modes;
        $source = $this->source;

        if (($mode & self::ACCEL) && $this->available(self::ACCEL)) {
            $header = 'X-Accel-Redirect';
            // $source = resolveInternal($source); // soon
        } elseif (($mode & self::SENDFILE) && $this->available(self::SENDFILE)) {
            $header = 'X-Sendfile';
        }

        // Send headers with, eg.: Content-Disposition: attachment; ...
        Response::download($this->filename);

        if ($fallback) {
            // If fallback enable, use PHP for send file
            File::output($this->source);
        } elseif ($header) {
            // If available, use server module
            Response::header($header, $source);
        } else {
            $this->detectMisconfiguration();
        }
    }

    private function detectMisconfiguration()
    {
        $modes = $this->modes;

        if ($modes & (self::ACCEL | self::SENDFILE)) {
            $message = 'ACCEL and SENDFILE are not supported by server';
        } elseif ($modes & self::ACCEL) {
            $message = 'ACCEL is not supported by server';
        } elseif ($modes & self::SENDFILE) {
            $message = 'SENDFILE are not supported by server';
        } else {
            $message = 'No supported file delivery mode was configured';
        }

        throw new Exception($message, 0, 3);
    }
}
