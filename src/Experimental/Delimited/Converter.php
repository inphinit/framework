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

class Converter
{
    /** @var int `\n` and `\r` (and `\t` in the `tsv()` method) will be replaced by single spaces */
    const WHITESPACE_REPLACE = 1;

    /** @var int `\n` and `\r` (and `\t` in the `tsv()` method) will be quoted with slashs */
    const WHITESPACE_SLASH = 2;

    private $source;
    private $whitespaceMode;

    /**
     * Set reader
     *
     * @param \Inphinit\Experimental\Delimited\Reader $reader
     * @throws \Inphinit\Exception
     */
    public function __construct(Reader $reader)
    {
        $this->source = $reader;
        $this->whitespaceMode = self::WHITESPACE_REPLACE;
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

        $this->whitespaceMode = $mode;
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
        $source = $this->source;

        $escape = $enclosure === '' ? null : $enclosure . $enclosure;
        $escapes = "\r\n";

        $previous_flags = $source->setFlags(Reader::SKIP_BLANK | Reader::SKIP_HEADER);
        $source->refresh();

        $whitespace_mode = $this->whitespaceMode;

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as &$field) {
                if ($whitespace_mode === self::WHITESPACE_REPLACE) {
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

        // Restores previous flags
        $source->setFlags($previous_flags);
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
        $source = $this->source;

        $tab = "\t";
        $escapes = "\t\r\n";

        $previous_flags = $source->setFlags(Reader::SKIP_BLANK | Reader::SKIP_HEADER);
        $source->refresh();

        $whitespace_mode = $this->whitespaceMode;

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as &$field) {
                if ($whitespace_mode === self::WHITESPACE_REPLACE) {
                    $field = str_replace($escapes, ' ', $field);
                } else {
                    $field = addcslashes($field, $escapes);
                }
            }

            fwrite($handle, implode($tab, $fields) . $eol);
        }

        fclose($handle);

        // Restores previous flags
        $source->setFlags($previous_flags);
        $source->refresh();

        return $this;
    }

    /**
     * Save reader to JSON
     *
     * @param string $path
     * @param bool $pairs
     * @param int $jsonFlags
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function json($path, $pairs = true, $jsonFlags = 0)
    {
        $eol = $jsonFlags & JSON_PRETTY_PRINT ? "\r\n" : '';
        $skip_comma = true;
        $source = $this->source;

        if ($pairs) {
            $flags = Reader::MODE_COLUMN | Reader::SKIP_BLANK | Reader::SKIP_HEADER;
        } else {
            $flags = Reader::SKIP_BLANK | Reader::SKIP_HEADER;
        }

        $previous_flags = $source->setFlags($flags);

        $handle = $this->openSaveStream($path);

        $source->refresh();

        fwrite($handle, '[' . $eol);

        while (($fields = $source->fetch()) !== false) {
            if ($skip_comma) {
                $skip_comma = false;
            } else {
                fwrite($handle, ',' . $eol);
            }

            fwrite($handle, json_encode($fields, $jsonFlags));
        }

        fwrite($handle, ($skip_comma ? '' : $eol) . ']');
        fclose($handle);

        // Restores previous flags
        $source->setFlags($previous_flags);
        $source->refresh();

        return $this;
    }

    /**
     * Populate DOMElement with reader entries
     *
     * @param \DOMElement $element
     * @return \Inphinit\Experimental\Delimited\Converter
     */
    public function dom(\DOMElement $target, $parentTag = 'row')
    {
        $source = $this->source;

        $previous_flags = $source->setFlags(Reader::SKIP_BLANK | Reader::SKIP_HEADER);
        $source->refresh();

        $owner = $target->ownerDocument;
        $headers = $source->getHeaders();

        while (($fields = $source->fetch()) !== false) {
            $parent = $owner->createElement($parentTag);

            foreach ($fields as $index => $text) {
                $node = $owner->createElement($headers[$index]);
                $node->appendChild($owner->createTextNode($text));
                $parent->appendChild($node);
            }

            $target->appendChild($parent);
        }

        // Restores previous flags
        $source->setFlags($previous_flags);
        $source->refresh();

        return $this;
    }

    private function openSaveStream($path)
    {
        $handle = fopen($path, 'w');

        if ($handle !== false) {
            if (strpos($path, 'php://') !== 0 && flock($handle, LOCK_EX) === false) {
                fclose($handle);
            } else {
                return $handle;
            }

            $err = error_get_last();
            throw new Exception($err ? $err['message'] : 'Could not open file for writing', $err ? $err['type'] : 0, 4);
        }
    }

    private static function checkEndOfLine($eol)
    {
        if (is_string($eol) === false || empty($eol)) {
            throw new Exception('End-of-line must contain one or more characters');
        }
    }
}
