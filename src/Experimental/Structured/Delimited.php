<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Structured;

use Inphinit\Exception;

abstract class Delimited
{
    const TSV = 1;
    const JSON_INDEX = 2;
    const JSON_PAIRS = 3;
    const MODE_INDEX = 4;
    const MODE_COLUMN = 5;

    protected $stream;
    protected $indexSize;

    protected $enclosure = '"';
    protected $escape = '\\';
    protected $separator;
    protected $separators = array();

    protected $readingLength;

    protected $fillEntries;
    protected $headers;
    protected $streaming;
    protected $decoding = false;

    protected $eol = "\r\n";

    protected static $bom = "\xEF\xBB\xBF";
    protected static $nullChar = "\x00";

    /**
     * Open CSV file
     *
     * @param string $path
     * @param string $mode
     */
    public function __construct($path)
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->raise(3);
        }

        $this->stream = $handle;
        $this->setReadingLength(0, false);
        $this->boot();
    }

    /**
     * Enable/disable decoding
     *
     * @param bool $enable
     */
    public function enableDecoding($enable)
    {
        if (is_bool($enable) === false) {
            throw new Exception('A boolean value is expected');
        }

        $this->decoding = $enable;
    }

    /**
     * Set the length of CSV lines read
     *
     * @param int|null $length
     * @param bool $refresh
     */
    public function setReadingLength($length, $refresh = true)
    {
        if ($length !== null && (is_int($length) === false || $length < 0)) {
            throw new Exception('Invalid length');
        }

        if (PHP_VERSION_ID < 80000) {
            if ($length === null) $length = 0;
        } elseif ($length < 1) {
            $length = null;
        }

        if ($this->readingLength !== $length) {
            $this->boot();
        }

        $this->readingLength = $length;
    }

    /**
     * Set line break used by save() method
     *
     * @param string $eol
     */
    public function setEol($eol)
    {
        if (empty($eol) || is_string($eol) === false) {
            throw new Exception('Invalid line break');
        }

        $this->eol = $eol;
    }

    /**
     * Get headers from CSV file
     *
     * @return array<string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get file pointer resource
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Rewind the position of file pointer
     */
    public function rewind()
    {
        rewind($this->stream);
        $this->streaming = $this->getLine($this->separator) !== false;
    }

    /**
     * Fetch a row from file pointer and parse for CSV fields
     *
     * @param int $mode
     * @param bool $decoding
     * @return array|false
     */
    public function fetch($mode = 0)
    {
        $entry = false;

        if ($this->streaming) {
            if (($entry = $this->getLine($this->separator)) !== false) {
                $lineSize = count($entry);
                $headersSize = count($this->headers);

                if ($entry[0] === self::$nullChar && $lineSize === 1) {
                    return false;
                }

                if ($this->decoding) {
                    foreach ($entry as &$item) {
                        $item = stripcslashes($item);
                    }
                }

                if ($lineSize > $headersSize) {
                    array_splice($entry, $headersSize);
                } elseif ($lineSize < $headersSize) {
                    if ($this->fillEntries === null) {
                        $this->fillEntries = array_fill(0, $headersSize, '');
                    }

                    $entry += $this->fillEntries;
                }

                if ($mode === self::MODE_INDEX) {
                    foreach ($entry as &$item) {
                        $item = self::normalize($item);
                    }

                    return $entry;
                }

                $indexSize = $this->indexSize;

                for ($index = 0; $index < $indexSize; ++$index) {
                    $item = self::normalize($entry[$index]);
                    $header = $this->headers[$index];
                    $entry[$index] = $item;
                    $entry[$header] = $item;
                }

                if ($mode === self::MODE_COLUMN) {
                    for ($index = 0; $index < $indexSize; ++$index) {
                        unset($entry[$index]);
                    }
                }
            } else {
                $this->streaming = false;
            }
        }

        return $entry;
    }

    /**
     * Saves a copy of file in the following formats (supports php:// output streams):
     * - CSV: will generate a CSV file. Before saving the document you can use the setX, setY and setZ methods to change the output.
     * - TSV: in this format the separators are TABs.
     * - JSON_INDEX: will generate a file with `[["header 1","header2"],["foo","bar"],["baz","boo"]]`.
     * - JSON_PAIRS: will generate a file with `[{"header 1":"foo","header2":"bar"},{"header 1":"baz","header2":"boo"}]`.
     *
     * @param string $path
     * @param int $format
     */
    public function save($path, $format)
    {
        $handle = $this->saveStream($path);

        $bof = null;
        $eof = null;
        $firstEntryWritten = false;

        if ($format === self::JSON_INDEX) {
            $mode = self::MODE_INDEX;
            $bof = '[';
            $eof = ']';
        } elseif ($format === self::JSON_PAIRS) {
            $mode = self::MODE_COLUMN;
            $bof = '[';
            $eof = ']';
        } elseif ($format === self::TSV) {
            $mode = self::MODE_INDEX;
        } else {
            throw new Exception('Invalid output format: ' . $format);
        }

        $this->rewind();

        if ($bof) {
            fwrite($handle, $bof);
        }

        $tab = "\t";
        $space = ' ';
        $eol = $this->eol;

        if ($format === self::TSV) {
            $items = str_replace($tab, $space, $this->headers);
            fwrite($handle, implode($tab, $items) . $eol);
        } elseif ($format === self::JSON_INDEX) {
            fwrite($handle, json_encode($this->headers));
            $firstEntryWritten = true;
        }

        $originalDecoding = $this->decoding;

        $this->decoding = false;

        while ($items = $this->fetch($mode)) {
            if ($format === self::TSV) {
                $items = str_replace($tab, $space, $items);
                fwrite($handle, implode($tab, $items) . $eol);
            } else {
                if ($firstEntryWritten) {
                    fwrite($handle, ',');
                } else {
                    $firstEntryWritten = true;
                }

                fwrite($handle, json_encode($items));
            }
        }

        $this->decoding = $originalDecoding;

        if ($eof) {
            fwrite($handle, $eof);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Saves a copy of file in CSV format (supports php:// output streams)
     *
     * @param string $path
     */
    public function saveCsv($path, $separator = ',', $enclosure = '"', $escape = "\\", $eol = "\r\n")
    {
        $handle = $this->saveStream($path);

        $this->rewind();

        $tab = "\t";
        $space = ' ';
        $eol = $this->eol;
        $mode = self::MODE_INDEX;

        while ($items = $this->fetch($mode)) {
            fputcsv($handle, $items, $separator, $enclosure, $escape, $eol);
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function __destruct()
    {
        if ($this->stream) {
            fclose($this->stream);
        }
    }

    protected function getLine($separator)
    {
        if (feof($this->stream)) {
            return false;
        }

        return fgetcsv($this->stream, $this->readingLength, $separator, $this->enclosure, $this->escape);
    }

    protected function boot()
    {
        $this->streaming = false;

        $headers = null;
        $inferredSeparator = null;
        $separator = $this->separator;
        $hasSeparator = $this->separator !== null;
        $separators = $hasSeparator ? array($separator) : $this->separators;

        foreach ($separators as $separator) {
            rewind($this->stream);

            $headers = $this->getLine($separator);

            if ($headers === false) {
                $this->raise(4);
            }

            $indexSize = count($headers);

            if ($indexSize > 1) {
                $inferredSeparator = $separator;
                break;
            }
        }

        if ($inferredSeparator === null) {
            throw new Exception($hasSeparator ? "Invalid document for current separator: {$separator}" : 'Invalid document', 0, 3);
        }

        foreach ($headers as &$header) {
            $header = self::normalize($header);
        }

        $this->headers = $headers;
        $this->indexSize = $indexSize;
        $this->separator = $inferredSeparator;
        $this->streaming = true;
    }

    private static function normalize($input)
    {
        return strncmp($input, self::$bom, 3) === 0 ? substr($input, 3) : $input;
    }

    private static function raise($level)
    {
        $err = error_get_last();
        throw new Exception($err ? $err['message'] : 'Unknown error', $err ? $err['type'] : 0, $level);
    }

    private function saveStream($path)
    {
        $handle = fopen($path, 'w');

        if ($handle === false || (strpos($path, 'php://') !== 0 && flock($handle, LOCK_EX) === false)) {
            $this->raise(4);
        }

        return $handle;
    }
}

