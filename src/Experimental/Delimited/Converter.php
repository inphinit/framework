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
    /** @var string End-of-line as \r */
    const EOL_CR = "\r";

    /** @var string End-of-line as \r\n */
    const EOL_CRLF = "\r\n";

    /** @var string End-of-line as \n */
    const EOL_LF = "\n";

    private $source;

    /**
     * Set reader
     *
     * @param \Inphinit\Experimental\Delimited\Reader $reader
     * @throws \Inphinit\Exception
     */
    public function __construct(Reader $reader)
    {
        $this->source = $reader;
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
    public function csv($path, $separator = ',', $enclosure = '"', $eol = self::EOL_LF)
    {
        if (is_string($separator) === false || strlen($separator) !== 1) {
            throw new Exception('Separator must be a single byte character');
        }

        if (is_string($enclosure) === false || strlen($enclosure) !== 1) {
            throw new Exception('Enclosure must be a single byte character');
        }

        self::checkEndOfLine($eol);

        $handle = $this->openSaveStream($path);
        $escape = $enclosure === '' ? null : $enclosure . $enclosure;
        $source = $this->source;
        $mode = $source->getMode();

        $source->setMode(Reader::MODE_INDEX);
        $source->refresh();

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as &$field) {
                $field = addcslashes($field, self::EOL_CRLF);

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
    public function tsv($path, $eol = self::EOL_LF)
    {
        self::checkEndOfLine($eol);

        $handle = $this->openSaveStream($path);
        $source = $this->source;
        $mode = $source->getMode();
        $tab = "\t";
        $encode = self::EOL_CRLF . $tab;

        $source->setMode(Reader::MODE_INDEX);
        $source->refresh();

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as &$field) {
                $field = addcslashes($field, $encode);
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

        $eol = $flags & JSON_PRETTY_PRINT ? self::EOL_LF : '';
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
    public function dom(\DOMElement $element)
    {
        $source = $this->source;
        $headers = $source->getHeaders();
        $mode = $source->getMode();
        $owner = $element->ownerDocument;

        $source->setMode(Reader::MODE_INDEX | Reader::MODE_SKIP_HEADER);
        $source->refresh();

        while (($fields = $source->fetch()) !== false) {
            foreach ($fields as $index => $text) {
                $node = $owner->createElement($headers[$index]);
                $node->appendChild($owner->createTextNode($text));
                $element->appendChild($node);
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
        if (in_array($eol, array(self::EOL_CR, self::EOL_CRLF, self::EOL_LF)) === false) {
            throw new Exception('Invalid end-of-line', 0, 3);
        }
    }
}
