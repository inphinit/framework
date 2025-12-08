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

    /** @var int Skip empty lines */
    const SKIP_EMPTY = 4;

    /** @var int Skip the headers in the fetch() method */
    const SKIP_HEADER = 8;

    protected $separator;
    protected $separators = array();
    protected $stream;

    private $chunk = 0;
    private $converter;
    private $dto;
    private $eol = "\n";
    private $fillFields;
    private $filter;
    private $firstLine = true;
    private $headers = array();
    private $limitCount = 0;
    private $limitOffset = 0;
    private $lineIndex = -1;
    private $flags;
    private $noNextLine = false;
    private $totalFields;
    private $uninitialized = true;
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

        $this->flags = self::MODE_INDEX | self::SKIP_EMPTY | self::SKIP_HEADER;
        $this->stream = fopen($path, 'rb');

        if ($this->stream === false) {
            $err = error_get_last();
            throw new Exception($err ? $err['message'] : 'Unknown error', $err ? $err['type'] : 0, 3);
        }
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
        $this->boot();
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
        $this->uninitialized = true;
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
     * Set the behavior of the fetch() method, and returns the previously defined flags.
     *
     * @param int $flags
     * @throws \Inphinit\Exception
     * @return int
     */
    public function setFlags($flags)
    {
        $validFlags = self::MODE_COLUMN | self::MODE_INDEX | self::SKIP_EMPTY | self::SKIP_HEADER;

        if (is_int($flags) === false || ($flags & ~$validFlags) !== 0) {
            throw new Exception('Invalid flags');
        }

        if (($flags & self::MODE_COLUMN) && ($flags & self::MODE_INDEX)) {
            throw new Exception('MODE_COLUMN and MODE_INDEX cannot be used at the same time');
        }

        $last = $this->flags;

        $this->flags = $flags;

        return $last;
    }

    /**
     * Enable/disable strict mode.
     * Note: When strict mode is enabled, the header must include at least two columns,
     * using one of the expected separators to delimit them.
     *
     * @param bool $enable
     */
    public function setStrictMode($enable)
    {
        if (is_bool($enable) === false) {
            throw new Exception('$enable argument must be of type bool, ' . gettype($enable) . ' given');
        }

        $this->strictMode = $enable;
    }

    /**
     * Set custom filter for fields, and returns the previously defined filter (if any).
     * Note: If the callback returns false, the line will be ignored.
     * Note: Values can be changed by reference.
     *
     * @param callable $filter
     * @throws \Inphinit\Exception
     * @return callable
     */
    public function setFilter(callable $filter)
    {
        $last = $this->filter;

        $this->filter = $filter;

        return $last;
    }

    /**
     * Set the maximum number of rows returned and skips a certain
     * number of rows before starting to return rows
     *
     * @param int $count  Set limit
     * @param int $offset Set offset
     */
    public function setLimit($count, $offset = 0)
    {
        $this->limitCount = $count > 0 ? $count : 0;
        $this->limitOffset = $offset > 0 ? $offset : 0;
        $this->uninitialized = true;
    }

    /**
     * Fetch a row from file pointer
     *
     * @throws \Inphinit\Exception
     * @return array<int, string>|array<string, string>|false
     */
    public function fetch()
    {
        $this->boot();

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
        } elseif ($this->flags & self::MODE_COLUMN) {
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

        if ($entry === false) {
            $this->noNextLine = true;
            return null;
        }

        // Skip empty lines
        if (($this->flags & self::SKIP_EMPTY) && trim($entry) === '') {
            return $this->getLine($separator);
        }

        $fields = $this->parse($separator, $entry);

        if ($fields !== false) {
            return $fields;
        }

        $this->noNextLine = true;
        return null;
    }

    private function rewindStream()
    {
        $this->lineIndex = -1;

        rewind($this->stream);

        // Skip BOM
        if (fread($this->stream, 3) !== self::$bom) {
            rewind($this->stream);
        }
    }

    private function boot()
    {
        if ($this->uninitialized) {
            $this->uninitialized = false;
            $this->firstLine = true;
            $this->lineIndex = -1;
            $this->noNextLine = false;

            $fields = null;
            $inferredSeparator = null;
            $totalFields = 0;

            // automatically detects the appropriate separator for the document
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
                if ($this->strictMode) {
                    throw new Exception('No separator was detected in the document header', 0, 3);
                } else {
                    $inferredSeparator = '';
                }
            }

            $this->filterFields($fields);

            $this->headers = $fields;
            $this->separator = $inferredSeparator;
            $this->totalFields = $totalFields;

            if ($this->flags & self::SKIP_HEADER) {
                $this->firstLine = false;
            } else {
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
