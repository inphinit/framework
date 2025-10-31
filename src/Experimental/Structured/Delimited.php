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
    protected $chunk;
    protected $separator;
    protected $enclosure = '"';
    protected $escape = '';
    protected $separators = array();
    protected $eol = "\r\n";

    protected static $bom = "\xEF\xBB\xBF";
    protected static $nullChar = "\x00";

    private $useHeaders;
    private $headers = array();
    private $fillEntries;
    private $firstLine = true;
    private $decoding = false;
    private $streamEof = false;
    private $indexSize;
    private $saveStream;

    /**
     * Open file with delimiter-separated values
     *
     * @param string $path  Set file path
     * @param bool $headers Set true to treat the first line as headers, set false otherwise
     * @throws \Inphinit\Exception
     */
    public function __construct($path, $headers = true)
    {
        $this->stream = fopen($path, 'r');

        if ($this->stream === false) {
            self::raise(3);
        }

        $this->useHeaders = $headers;

        $this->boot();
    }

    /**
     * Set maximum line length
     *
     * @param int|null $length
     * @param bool     $refresh
     * @throws \Inphinit\Exception
     */
    public function setChunk($length, $refresh = true)
    {
        if ($length !== null && (is_int($length) === false || $length < 0)) {
            throw new Exception('Invalid length');
        }

        if (PHP_VERSION_ID < 80000) {
            if ($length === null) {
                $length = 0;
            }
        } elseif ($length < 1) {
            $length = null;
        }

        $this->updateControl('chunk', $length, $refresh);
    }

    /**
     * Set custom End of Line sequence used by `save()` and `saveCsv()` methods
     *
     * @param string $eol
     * @throws \Inphinit\Exception
     */
    public function setEol($eol)
    {
        $this->isValid('eol', $eol);
        $this->eol = $eol;
    }

    /**
     * Enable or disable decoding of escaped sequences (e.g. `\t`, `\n`, `\\`) in field values.
     *
     * This is an optional convenience feature for CSV and TSV formats, commonly used
     * in real-world TSV files where escaping is not formally specified but often applied.
     * When enabled, each field value will be processed through `stripcslashes()`.
     *
     * This does not affect file export (`save()` and `saveCsv()`), ensuring data integrity. Decoding is only
     * applied at read-time and should be used when you expect backslash-escaped sequences
     * in your source document.
     *
     * @param bool $enable
     * @throws \Inphinit\Exception
     */
    public function enableDecoding($enable)
    {
        if (is_bool($enable) === false) {
            throw new Exception('A boolean value is expected');
        }

        $this->decoding = $enable;
    }

    /**
     * Get headers from file
     *
     * @return array<int, string>
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

        if ($this->useHeaders === false || $this->getLine($this->separator) !== false) {
            $this->streamEof = false;
        }
    }

    /**
     * Fetch a row from file pointer
     *
     * @param int $mode
     * @throws \Inphinit\Exception
     * @return array<string, string>|array<int, string>|false
     */
    public function fetch($mode = 0)
    {
        if ($this->streamEof) {
            return false;
        }

        if ($this->useHeaders === false && $mode === self::MODE_COLUMN) {
            throw new Exception('This instance is configured to not use headers');
        }

        $entry = $this->getLine($this->separator);

        if ($entry === false || $entry[0] === self::$nullChar) {
            $this->streamEof = true;
            return false;
        }

        if ($this->firstLine) {
            self::withoutBom($entry[0]);
            $this->firstLine = false;
        }

        if ($this->decoding) {
            foreach ($entry as &$item) {
                $item = stripcslashes($item);
            }
        }

        $indexSize = $this->indexSize;
        $lineSize = count($entry);

        if ($lineSize > $indexSize) {
            array_splice($entry, $indexSize);
        } elseif ($lineSize < $indexSize) {
            if ($this->fillEntries === null) {
                $this->fillEntries = array_fill(0, $indexSize, '');
            }

            $entry += $this->fillEntries;
        }

        if ($this->useHeaders && $mode !== self::MODE_INDEX) {
            for ($index = 0; $index < $indexSize; ++$index) {
                $header = $this->headers[$index];
                $entry[$header] = $entry[$index];
            }

            if ($mode === self::MODE_COLUMN) {
                for ($index = 0; $index < $indexSize; ++$index) {
                    unset($entry[$index]);
                }
            }
        }

        return $entry;
    }

    /**
     * Saves a copy to file in the following formats (supports `php://` output streams):
     * - TSV: in this format the separators are TABs
     * - JSON_INDEX: will generate a file with `[["header 1","header2"],["foo","bar"],["baz","boo"]]`
     * - JSON_PAIRS: will generate a file with `[{"header 1":"foo","header2":"bar"},{"header 1":"baz","header2":"boo"}]`
     *
     * @param string $path
     * @param int $format
     * @throws \Inphinit\Exception
     */
    public function save($path, $format)
    {
        $handle = $this->openSaveStream($path);

        $bof = null;
        $eof = null;
        $firstEntryWritten = false;

        if ($format === self::JSON_INDEX) {
            $mode = self::MODE_INDEX;
            $bof = '[';
            $eof = ']';
        } elseif ($format === self::JSON_PAIRS) {
            if ($this->useHeaders === false) {
                throw new Exception('This instance is configured to not use headers');
            }

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

        $eol = $this->eol;
        $tab = "\t";
        $space = ' ';

        if ($this->useHeaders) {
            if ($format === self::TSV) {
                $items = str_replace($tab, $space, $this->headers);
                fwrite($handle, implode($tab, $items) . $eol);
            } elseif ($format === self::JSON_INDEX) {
                fwrite($handle, json_encode($this->headers));
                $firstEntryWritten = true;
            }
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

        $this->closeSaveStream();
        $this->rewind();
    }

    /**
     * Saves a copy of file in CSV format (supports `php://` output streams)
     *
     * @param string      $path      The output file path or stream (e.g., 'php://output')
     * @param string|null $separator The CSV separator character for saving. Defaults to current instance separator
     * @param string|null $enclosure The CSV enclosure character for saving. Defaults to current instance enclosure
     * @param string|null $escape    The CSV escape character for saving. Defaults to current instance escape
     * @throws \Inphinit\Exception   If any of the optional parameters are invalid
     */
    public function saveCsv($path, $separator = null, $enclosure = null, $escape = null)
    {
        $this->isValid('separator', $separator, true);
        $this->isValid('enclosure', $enclosure, true);
        $this->isValid('escape', $escape, true);

        $handle = $this->openSaveStream($path);

        $this->rewind();

        $eol = $this->eol;
        $mode = self::MODE_INDEX;

        while ($items = $this->fetch($mode)) {
            if (PHP_VERSION_ID < 80100) {
                fputcsv($handle, $items, $separator, $enclosure, $escape);
            } else {
                fputcsv($handle, $items, $separator, $enclosure, $escape, $eol);
            }
        }

        $this->closeSaveStream();
        $this->rewind();
    }

    public function __destruct()
    {
        $this->closeSaveStream();

        fclose($this->stream);
    }

    protected function isValid($property, &$value, $fallback = false)
    {
        if ($fallback && $value === null) {
            $value = $this->{$property};
        } elseif (is_string($value) === false || empty($value)) {
            throw new Exception('Invalid value for ' . $property . ' property', 0, 3);
        }
    }

    protected function updateControl($property, $value, $refresh)
    {
        if ($this->{$property} !== $value) {
            $this->{$property} = $value;

            if ($refresh) {
                $this->boot();
            }
        }
    }

    protected function getLine($separator)
    {
        while (feof($this->stream) === false) {
            $line = fgetcsv($this->stream, $this->chunk, $separator, $this->enclosure, $this->escape);

            if (isset($line[1]) || empty($line[0]) === false) {
                return $line;
            }
        }

        return false;
    }

    private function boot()
    {
        $indexSize = 0;
        $entry = null;
        $inferredSeparator = null;

        foreach ($this->separators as $separator) {
            rewind($this->stream);

            $entry = $this->getLine($separator);

            if ($entry === false) {
                self::raise(4);
            }

            $indexSize = count($entry);

            if ($indexSize > 1) {
                $inferredSeparator = $separator;
                break;
            }
        }

        if ($inferredSeparator === null) {
            throw new Exception('Invalid document', 0, 3);
        }

        $this->indexSize = $indexSize;
        $this->separator = $inferredSeparator;

        if ($this->useHeaders) {
            self::withoutBom($entry[0]);
            $this->firstLine = false;
            $this->headers = $entry;
        } else {
            rewind($this->stream);
        }
    }

    private static function raise($level)
    {
        $err = error_get_last();
        throw new Exception($err ? $err['message'] : 'Unknown error', $err ? $err['type'] : 0, $level);
    }

    private function openSaveStream($path)
    {
        $handle = fopen($path, 'w');

        if ($handle === false || (strpos($path, 'php://') !== 0 && flock($handle, LOCK_EX) === false)) {
            self::raise(4);
        }

        return $this->saveStream = $handle;
    }

    private function closeSaveStream()
    {
        if ($this->saveStream) {
            $meta = stream_get_meta_data($this->saveStream);

            if (strpos($meta['uri'], 'php://') !== 0) {
                flock($this->saveStream, LOCK_UN);
            }

            fclose($this->saveStream);
            $this->saveStream = null;
        }
    }

    private static function withoutBom(&$item)
    {
        if (strncmp($item, self::$bom, 3) === 0) {
            $item = substr($item, 3);
        }
    }
}
