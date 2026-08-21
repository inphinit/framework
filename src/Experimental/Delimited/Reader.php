<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Delimited;

use Inphinit\Exception;

abstract class Reader
{
    /** @var int Fields are returned as associative arrays (note: Duplicate column names will be overwritten) */
    const MODE_COLUMN = 1;

    /** @var int Skip blank lines, containing only whitespace or empty */
    const SKIP_BLANK = 2;

    /** @var int Skip the headers in the `fetch()` method */
    const SKIP_HEADER = 4;

    /** @var int The header must include at least two columns */
    const STRICT = 8;

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
    private $limitCount = 1000;
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

        $this->flags = self::SKIP_BLANK | self::SKIP_HEADER;
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
     * Note: To ensure the key format, use `setFilter()` method.
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
     * Set the behavior of the `fetch()` method, and returns the previously defined flags.
     *
     * @param int $flags
     * @throws \Inphinit\Exception
     * @return int
     */
    public function setFlags($flags)
    {
        $valid_flags = self::MODE_COLUMN | self::SKIP_BLANK | self::SKIP_HEADER | self::STRICT;

        if (is_int($flags) === false || ($flags & ~$valid_flags) !== 0) {
            throw new Exception('Invalid flags');
        }

        $last = $this->flags;

        $this->flags = $flags;

        return $last;
    }

    /**
     * Set a custom filter that can be used to skip specific rows by returning `false`
     * in the callback, or simply to modify the values received by reference.
     *
     * Note: For headers, the filter is only used to modify the values.
     * Note: When defining a callback, the previous callback will be returned.
     *
     * E.g:
     *
     * ``` php
     * $handle->setFilter(function (array &$fields, $index) {
     *   if ($index === 0) {
     *     // Convert header
     *     $fields = str_to_snake_case($fields);
     *   } else {
     *     // Convert rows
     *     $field = array_map('stripcslashes', $field);
     *   }
     * });
     * ```
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
     * number of rows before starting to return rows.
     * Note: If this method is not used, it will be limited to 1000 rows.
     *
     * @param int $count  Set limit rows (Note: Set 0 for unlimited).
     * @param int $offset Set offset row.
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

        $limit_count = $this->limitCount;
        $limit_offset = $this->limitCount + $this->limitOffset;

        while (($fields = $this->getLine($this->separator)) !== null) {
            if ($limit_count !== 0 && $this->lineIndex > $limit_offset) {
                $this->noNextLine = true;
                return false;
            }

            if ($this->firstLine) {
                $this->firstLine = false;
            }

            if ($this->filterFields($fields) === false) {
                continue;
            }

            $size = count($fields);

            $total_fields = $this->totalFields;

            if ($size < $total_fields) {
                if ($this->fillFields === null) {
                    $this->fillFields = array_fill(0, $total_fields, '');
                }

                $fields += $this->fillFields;
            } elseif ($size !== $total_fields) {
                array_splice($fields, $total_fields);
            }

            if ($this->dto !== null) {
                $class = $this->dto;
                $headers = $this->headers;
                $instance = new $class;

                foreach ($fields as $index => $text) {
                    $instance->{$headers[$index]} = $text;
                }

                return $instance;
            }

            if ($this->flags & self::MODE_COLUMN) {
                $fields = array_combine($this->headers, $fields);
            }

            return $fields;
        }

        return false;
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

        $stream = $this->stream;
        $chunk = $this->chunk;
        $eol = $this->eol;
        $entry = '';

        if ($this->flags & self::SKIP_BLANK) {
            while ($entry !== false && trim($entry) === '') {
                $entry = stream_get_line($stream, $chunk, $eol);
            }
        } else {
            $entry = stream_get_line($stream, $chunk, $eol);
        }

        if ($entry === false) {
            $this->noNextLine = true;
            return null;
        }

        ++$this->lineIndex;

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
            $inferred_separator = null;
            $total_fields = 0;

            // automatically detects the appropriate separator for the document
            foreach ($this->separators as $separator) {
                $this->rewindStream();

                $fields = $this->getLine($separator);

                if (is_array($fields) === false) {
                    $inferred_separator = '';
                    break;
                }

                $total_fields = count($fields);

                if ($total_fields > 1) {
                    $inferred_separator = $separator;
                    break;
                }
            }

            if ($total_fields === 1) {
                if ($this->flags & self::STRICT) {
                    throw new Exception('No separator was detected in the document header', 0, 3);
                } else {
                    $inferred_separator = '';
                }
            }

            $this->filterFields($fields);

            $this->headers = $fields;
            $this->separator = $inferred_separator;
            $this->totalFields = $total_fields;

            if ($this->flags & self::SKIP_HEADER) {
                $this->firstLine = false;
            } else {
                $this->rewindStream();
            }

            $offset = $this->limitOffset;

            if ($offset > 0) {
                while ($this->lineIndex < $offset) {
                    if ($this->getLine($inferred_separator) === null) {
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
