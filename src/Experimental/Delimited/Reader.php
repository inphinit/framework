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

    private $chunk;
    private $converter;
    private $dto;
    private $eol = "\n";
    private $fillFields;
    private $firstLine = true;
    private $headers = array();
    private $index = -1;
    private $indexSize;
    private $mode;
    private $noNextLine = false;
    private $sanitize;
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
     * Set custom sanitize for fields
     *
     * @param callable $sanitize
     * @throws \Inphinit\Exception
     */
    public function setSanitize(callable $sanitize)
    {
        $this->sanitize = $sanitize;
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

        if ($this->firstLine) {
            $this->firstLine = false;
        }

        $this->sanitizeFields($fields);

        $size = count($fields);

        if ($size < $this->indexSize) {
            if ($this->fillFields === null) {
                $this->fillFields = array_fill(0, $this->indexSize, '');
            }

            $fields += $this->fillFields;
        } elseif ($size !== $this->indexSize) {
            array_splice($fields, $this->indexSize);
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

        $entry = stream_get_line($this->stream, $this->chunk, $this->eol);

        $fields = false;

        if ($entry === false || ($fields = $this->parse($separator, $entry)) === false) {
            $this->noNextLine = true;
            return null;
        }

        return $fields;
    }

    private function rewindStream($refresh = false)
    {
        rewind($this->stream);

        // Skip BOM
        if (fread($this->stream, 3) !== self::$bom) {
            rewind($this->stream);
        }

        $this->firstLine = true;
        $this->index = -1;
        $this->noNextLine = false;
    }

    private function boot()
    {
        $fields = null;
        $indexSize = 0;
        $inferredSeparator = null;

        if ($this->separator === null) {
            foreach ($this->separators as $separator) {
                $this->rewindStream();

                $fields = $this->getLine($separator);

                if (is_array($fields) === false) {
                    $inferredSeparator = '';
                    break;
                }

                $indexSize = count($fields);

                if ($indexSize > 1) {
                    $inferredSeparator = $separator;
                    break;
                }
            }
        }

        if ($fields !== null) {
            throw new Exception('Invalid document', 0, 3);
        }

        if ($indexSize === 1) {
            $inferredSeparator = '';
        }

        $this->sanitizeFields($fields);

        $this->headers = $fields;
        $this->indexSize = $indexSize;
        $this->separator = $inferredSeparator;

        if ($this->mode & self::MODE_SKIP_HEADER) {
            $this->firstLine = false;
        } else {
            $this->rewindStream();
        }
    }

    private function sanitizeFields(array &$fields)
    {
        $index = ++$this->index;

        if ($this->sanitize !== null) {
            $sanitize = $this->sanitize;

            foreach ($fields as &$field) {
                $field = $sanitize($field, $index);
            }
        }
    }
}
