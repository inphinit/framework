<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Filesystem;

use Inphinit\Uri;

class Size
{
    private $path;
    private $size;
    private $isWin = false;

    const OS = 1;
    const CURL = 2;
    const WINDOWS = 4;

    // const kB = 8;
    // const MB = 16;
    // const MB = 32;
    // const GB = 64;
    // const TB = 128;
    // const PB = 256;
    // const EB = 512;
    // const ZB = 1024;
    // const YB = 2048;
    // const RB = 4096;
    // const QB = 8192;

    /**
     * @param string $path
     */
    public function __construct($path, $mode = null)
    {
        if ($mode === null) {
            $this->mode = self::OS;
        } else {
            $this->mode = $mode;
        }

        if ($this->size === null) {
            $this->path = realpath($path);
            $this->isWin = stripos(PHP_OS, 'WIN') === 0;
        }
    }

    // public function format($format === null)
    // {
    //     $this->get();

    //     if ($format === null) {

    //     } elseif ($format & self::kB) {

    //     } elseif ($format & self::MB) {

    //     } elseif ($format & self::GB) {

    //     } elseif ($format & self::TB) {

    //     } elseif ($format & self::PB) {

    //     } elseif ($format & self::EB) {

    //     } elseif ($format & self::ZB) {

    //     } elseif ($format & self::YB) {

    //     } elseif ($format & self::RB) {

    //     } elseif ($format & self::QB) {

    //     }
    // }

    public function __toString()
    {
        $this->get();

        return $this->size;
    }

    private function get()
    {
        if ($this->size === null && $this->mode & self::OS) {
            $this->fromOS();
        }

        if ($this->size === null && $this->mode & self::CURL) {
            $this->fromCurl();
        }

        if ($this->isWin && $this->size === null && $this->mode & self::WINDOWS) {
            $this->fromWindows();
        }
    }

    private function fromOS()
    {
        if (function_exists('shell_exec')) {
            $arg = escapeshellarg($this->path);

            if (stripos(PHP_OS, 'WIN') === 0) {
                $command = 'for %F in (' . $arg . ') do @echo %~zF';
            } else {
                $command = 'stat -c %s ' . $arg;
            }

            $response = shell_exec($command);

            if ($response) {
                $response = trim($response);
            }

            if (is_numeric($response)) {
                $this->size = $response;
            }
        }
    }

    private function fromCurl()
    {
        if (function_exists('curl_init')) {
            $handle = curl_init('file://' . rawurlencode($this->path));

            if ($handle !== false) {
                curl_setopt($handle, CURLOPT_NOBODY, true);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($handle, CURLOPT_HEADER, false);

                if (curl_exec($handle)) {
                    $this->size = curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                }

                curl_close($handle);
            }
        }
    }

    private function fromWindows()
    {
        if (class_exists('com', false)) {
            $obj = new com('Scripting.FileSystemObject');

            if ($file = $obj->GetFile($file)) {
                $this->size = $file->size;
            }
        }
    }
}
