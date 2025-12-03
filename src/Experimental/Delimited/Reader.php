<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Delimited;

use Inphinit\Exception;

abstract class Reader
{
    /** @var int Fields are returned as associative arrays */
    const MODE_COLUMN = 1;

    /** @var int Fields are returned as array with enumerated indices */
    const MODE_INDEX = 2;

    /** @var int Skip the headers in the fetch() method */
    const MODE_SKIP_HEADER = 4;

    protected $separator;
    protected $separators = array();
    protected $stream;

    private $chunk = 0;
    private $converter;
    private $dto;
    private $eol = "\n";
    private $fillFields;
    private $firstLine = true;
    private $headers = array();
    private $limitCount = 0;
    private $limitOffset = 0;
    private $lineIndex = -1;
    private $mode;
    private $noNextLine = false;
    private $filter;
    private $totalFields;
    private static $bom = "\xEF\xBB\xBF";

    /**
     * Open file
     *
     * @param string $path
     * @throws \Inphinit\Exception
     */
    public function __construct($path)
    {
        if (method_exists($this, 'parse') === false) {
            throw new Exception('The ' . get_class($this) . ' class does not have the parse() method', 0, 3);
        }

        $this->mode = self::MODE_INDEX | self::MODE_SKIP_HEADER;
        $this->stream = fopen($path, 'r');

        if ($this->stream === false) {
            $err = error_get_last();
            throw new Exception($err ? $err['message'] : 'Unknown error', $err ? $err['type'] : 0, 3);
        }

        $this->boot();
    }

    public function __destruct()
    {
        if ($this->stream) {
            fclose($this->stream);
        }
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
     * Rewind the position of file pointer and refresh headers
     */
    public function refresh()
    {
        $this->boot();
    }

    /**
     * Set maximum line length
     *
     * @param int|null $length
     * @throws \Inphinit\Exception
     */
    public function setChunk($length)
    {
        if ($length !== null && is_int($length) === false) {
            throw new Exception('Invalid length');
        }

        if ($length === null || $length < 0) {
            $length = 0;
        }

        $this->chunk = $length;
    }

    /**
     * Set Data Transfer Object class
     *
     * @param string|null $dto
     */
    public function setDataTransferObject($dto)
    {
        if ($dto !== null && class_exists($dto) === false) {
            throw new Exception("{$dto} class not found");
        }

        $this->dto = $dto;
    }

    /**
     * Set end-of-line
     *
     * @param string $eol
     */
    public function setEndOfLine($eol)
    {
        if (is_string($eol) === false || empty($eol)) {
            throw new Exception('Invalid end-of-line');
        }

        $this->eol = $eol;
    }

    /**
     * Set the behavior of the fetch() method
     *
     * @param int  $mode
     * @throws \Inphinit\Exception
     */
    public function setMode($mode)
    {
        $validModes = self::MODE_COLUMN | self::MODE_INDEX | self::MODE_SKIP_HEADER;

        if (is_int($mode) === false || ($mode & ~$validModes) !== 0) {
            throw new Exception('Invalid mode');
        }

        if (($mode & self::MODE_COLUMN) && ($mode & self::MODE_INDEX)) {
            throw new Exception('MODE_COLUMN and MODE_INDEX cannot be used at the same time');
        }

        $this->mode = $mode;
    }

    /**
     * Get the behavior of the fetch() method
     *
     * @return int
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Set custom filter for fields
     * Note: If the callback returns false, the line will be ignored.
     * Note: Values can be changed by reference.
     *
     * @param callable $filter
     * @throws \Inphinit\Exception
     */
    public function setFilter(callable $filter)
    {
        $this->filter = $filter;
    }

    /**
     * Set the maximum number of rows returned and skips a certain
     * number of rows before starting to return rows
     *
     * Note: This method will perform the refresh automatically.
     *
     * @param int $count  Set limit
     * @param int $offset Set offset
     */
    public function limit($count, $offset = 0)
    {
        $this->limitCount = $count > 0 ? $count : 0;
        $this->limitOffset = $offset > 0 ? $offset : 0;

        $this->refresh();
    }

    /**
     * Fetch a row from file pointer
     *
     * @throws \Inphinit\Exception
     * @return array<int, string>|array<string, string>|false
     */
    public function fetch()
    {
        $fields = $this->getLine($this->separator);

        if ($fields === null) {
            return false;
        }

        if ($this->limitCount !== 0 && $this->lineIndex > ($this->limitCount + $this->limitOffset)) {
            $this->noNextLine = true;
            return false;
        }

        if ($this->firstLine) {
            $this->firstLine = false;
        }

        if ($this->filterFields($fields) === false) {
            return $this->fetch();
        }

        $size = count($fields);

        if ($size < $this->totalFields) {
            if ($this->fillFields === null) {
                $this->fillFields = array_fill(0, $this->totalFields, '');
            }

            $fields += $this->fillFields;
        } elseif ($size !== $this->totalFields) {
            array_splice($fields, $this->totalFields);
        }

        if ($this->dto !== null) {
            $class = $this->dto;
            $headers = $this->headers;
            $instance = new $class;

            foreach ($fields as $index => $text) {
                $instance->{$headers[$index]} = $text;
            }

            return $instance;
        } elseif ($this->mode & self::MODE_COLUMN) {
            $fields = array_combine($this->headers, $fields);
        }

        return $fields;
    }

    /**
     * Get a `Converter` instance.
     *
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function converter()
    {
        if ($this->converter === null) {
            $this->converter = new Converter($this);
        }

        return $this->converter;
    }

    private function getLine($separator)
    {
        if ($this->noNextLine) {
            return null;
        }

        ++$this->lineIndex;

        $entry = stream_get_line($this->stream, $this->chunk, $this->eol);

        $fields = false;

        if ($entry === false || ($fields = $this->parse($separator, $entry)) === false) {
            $this->noNextLine = true;
            return null;
        }

        return $fields;
    }

    private function rewindStream()
    {
        rewind($this->stream);

        // Skip BOM
        if (fread($this->stream, 3) !== self::$bom) {
            rewind($this->stream);
        }
    }

    private function boot()
    {
        $this->firstLine = true;
        $this->lineIndex = -1;
        $this->noNextLine = false;

        $fields = null;
        $inferredSeparator = null;
        $totalFields = 0;

        foreach ($this->separators as $separator) {
            $this->rewindStream();

            $fields = $this->getLine($separator);

            if (is_array($fields) === false) {
                $inferredSeparator = '';
                break;
            }

            $totalFields = count($fields);

            if ($totalFields > 1) {
                $inferredSeparator = $separator;
                break;
            }
        }

        if ($totalFields === 1) {
            $inferredSeparator = '';
        }

        $this->filterFields($fields);

        $this->headers = $fields;
        $this->totalFields = $totalFields;
        $this->separator = $inferredSeparator;

        if ($this->mode & self::MODE_SKIP_HEADER) {
            $this->firstLine = false;
        } else {
            $this->lineIndex = -1;
            $this->rewindStream();
        }

        $offset = $this->limitOffset;

        if ($offset > 0) {
            while ($this->lineIndex < $offset) {
                if ($this->getLine($inferredSeparator) === null) {
                    break;
                }
            }
        }
    }

    private function filterFields(array &$fields)
    {
        if ($this->filter !== null) {
            $callback = $this->filter;

            if ($callback($fields, $this->lineIndex) === false) {
                return false;
            }
        }
    }
}
