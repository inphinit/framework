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

class Converter
{
    /** @var int `\n` and `\r` (and `\t` in the tsv() method) will be replaced by single spaces */
    const WHITESPACE_REPLACE = 1;

    /** @var int `\n` and `\r` (and `\t` in the tsv() method) will be quoted with slashs */
    const WHITESPACE_SLASH = 2;

    private $source;
    private $whiteSpaceMode;

    /**
     * Set reader
     *
     * @param \Inphinit\Experimental\Delimited\Reader $reader
     * @throws \Inphinit\Exception
     */
    public function __construct(Reader $reader)
    {
        $this->source = $reader;
        $this->whiteSpaceMode = self::WHITESPACE_REPLACE;
    }

    /**
     * Set the write mode for `\n`, `\r` and `\t` on field entries
     *
     * - WHITESPACE_REPLACE: Remove whitespaces
     * - WHITESPACE_SLASH: Quote whitespace with slashes
     *
     * @param int $mode
     * @throws \Inphinit\Exception
     */
    public function setWhitespaceMode($mode)
    {
        if ($mode !== self::WHITESPACE_REPLACE && $mode !== self::WHITESPACE_SLASH) {
            throw new Exception('Invalid whitespace mode');
        }

        $this->whiteSpaceMode = $mode;
    }

    /**
     * Save reader to CSV
     *
     * @param string $path
     * @param string $separator
     * @param string $enclosure
     * @param string $eol
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function csv($path, $separator = ',', $enclosure = '"', $eol = "\r\n")
    {
        if (is_string($separator) === false || strlen($separator) !== 1) {
            throw new Exception('Separator must be a single byte character');
        }

        if (is_string($enclosure) === false || strlen($enclosure) !== 1) {
            throw new Exception('Enclosure must be a single byte character');
        }

        self::checkEndOfLine($eol);

        $handle = $this->openSaveStream($path);
        $mode = $source->getMode();
        $source = $this->source;

        $escape = $enclosure === '' ? null : $enclosure . $enclosure;
        $escapes = "\r\n";

        $source->setMode(Reader::MODE_INDEX);
        $source->refresh();

        $whiteSpaceMode = $this->whiteSpaceMode;

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as &$field) {
                if ($whiteSpaceMode === self::WHITESPACE_REPLACE) {
                    $field = str_replace($escapes, ' ', $field);
                } else {
                    $field = addcslashes($field, $escapes);
                }

                if ($escape !== null) {
                    $field = str_replace($enclosure, $escape, $field);
                }

                if (strpos($field, $separator) !== false || strpos($field, $enclosure) !== false) {
                    $field = $enclosure . $field . $enclosure;
                }
            }

            fwrite($handle, implode($separator, $fields) . $eol);
        }

        fclose($handle);

        // Restore original mode
        $source->setMode($mode);
        $source->refresh();

        return $this;
    }

    /**
     * Save reader to TSV
     *
     * @param string $path
     * @param string $eol
     * @throws \Inphinit\Exception
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function tsv($path, $eol = "\r\n")
    {
        self::checkEndOfLine($eol);

        $handle = $this->openSaveStream($path);
        $mode = $source->getMode();
        $source = $this->source;

        $tab = "\t";
        $escapes = "\t\r\n";

        $source->setMode(Reader::MODE_INDEX);
        $source->refresh();

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as &$field) {
                if ($whiteSpaceMode === self::WHITESPACE_REPLACE) {
                    $field = str_replace($escapes, ' ', $field);
                } else {
                    $field = addcslashes($field, $escapes);
                }
            }

            fwrite($handle, implode($tab, $fields) . $eol);
        }

        fclose($handle);

        // Restore original mode
        $source->setMode($mode);
        $source->refresh();

        return $this;
    }

    /**
     * Save reader to JSON
     *
     * @param string $path
     * @param bool $pairs
     * @param int $flags
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function json($path, $pairs = true, $flags = 0)
    {
        $handle = $this->openSaveStream($path);

        $eol = $flags & JSON_PRETTY_PRINT ? "\r\n" : '';
        $source = $this->source;
        $mode = $source->getMode();
        $skipComma = true;

        if ($pairs) {
            $source->setMode(Reader::MODE_COLUMN | Reader::MODE_SKIP_HEADER);
        } else {
            $source->setMode(Reader::MODE_INDEX);
        }

        $source->refresh();

        fwrite($handle, '[' . $eol);

        while (($fields = $source->fetch()) !== false) {
            if ($skipComma) {
                $skipComma = false;
            } else {
                fwrite($handle, ',' . $eol);
            }

            fwrite($handle, json_encode($fields, $flags));
        }

        fwrite($handle, ($skipComma ? '' : $eol) . ']');
        fclose($handle);

        // Restore original mode
        $source->setMode($mode);
        $source->refresh();

        return $this;
    }

    /**
     * Populate DOMElement with reader entries
     *
     * @param \DOMElement $element
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function dom(\DOMElement $parent, $headerTag = null, $valueTag = null, $valueTag = null)
    {
        $source = $this->source;
        $headers = $source->getHeaders();
        $mode = $source->getMode();
        $owner = $parent->ownerDocument;

        $source->setMode(Reader::MODE_INDEX | Reader::MODE_SKIP_HEADER);
        $source->refresh();

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as $index => $text) {
                $node = $owner->createElement($headers[$index]);
                $node->appendChild($owner->createTextNode($text));
                $parent->appendChild($node);
            }
        }

        // Restore original mode
        $source->setMode($mode);
        $source->refresh();

        return $this;
    }

    private function openSaveStream($path)
    {
        $handle = fopen($path, 'w');

        if ($handle === false || (strpos($path, 'php://') !== 0 && flock($handle, LOCK_EX) === false)) {
            $err = error_get_last();
            throw new Exception($err ? $err['message'] : 'Unknown error', $err ? $err['type'] : 0, 4);
        }

        return $handle;
    }

    private static function checkEndOfLine($eol)
    {
        if (is_string($eol) === false || empty($eol)) {
            throw new Exception('End-of-line must contain one or more characters');
        }
    }
}
