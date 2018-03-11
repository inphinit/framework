<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Dom;

use Inphinit\Helper;
use Inphinit\Storage;

class Document extends \DOMDocument
{
    private $xpath;
    private $internalErr;

    private $complete = false;
    private $simple = false;

    const XML = 1;
    const HTML = 2;
    const JSON = 3;

    const SIMPLE = 4;
    const MININAL = 5;
    const COMPLETE = 6;

    public function __construct($version = '1.0', $encoding = 'UTF-8')
    {
        parent::__construct($version, $encoding);
    }

    /**
     * Convert array in node elements
     *
     * @param array|\Traversable $data
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function fromArray(array $data)
    {
        if (empty($data)) {
            throw new DomException('Array is empty', 2);
        } elseif (count($data) > 1) {
            throw new DomException('Root array accepts only a key', 2);
        } elseif (Helper::seq($data)) {
            throw new DomException('Document accpet only a node', 2);
        }

        if ($this->documentElement) {
            $this->removeChild($this->documentElement);
        }

        $this->enableRestoreInternal(true);

        $this->generate($this, $data);

        self::raise(3);

        $this->enableRestoreInternal(false);
    }

    /**
     * Convert Dom to json string
     *
     * @param bool $format
     * @param int  $options `JSON_HEX_QUOT`, `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_NUMERIC_CHECK`, `JSON_PRETTY_PRINT`, `JSON_UNESCAPED_SLASHES`, `JSON_FORCE_OBJECT`, `JSON_PRESERVE_ZERO_FRACTION`, `JSON_UNESCAPED_UNICODE`, `JSON_PARTIAL_OUTPUT_ON_ERROR`. The behaviour of these constants is described in http://php.net/manual/en/json.constants.php
     *
     * @return string
     */
    public function toJson($format = Document::MININAL, $options = 0)
    {
        return json_encode($this->toArray($format), $options);
    }

    /**
     * Convert Dom to json string
     *
     * @param int $type
     * @throws \Inphinit\Experimental\Exception
     * @return array
     */
    public function toArray($type = Document::SIMPLE)
    {
        switch ($type) {
            case Document::MININAL:
                $this->simple = false;
                $this->complete = false;
            break;

            case Document::SIMPLE:
                $this->simple = true;
            break;

            case Document::COMPLETE:
                $this->complete = true;
            break;

            default:
                throw new DomException('Invalid type', 2);
        }

        return $this->getNodes($this->childNodes, true);
    }

    /**
     * Magic method, return a well-formed XML string
     *
     * Example:
     * <pre>
     * <code>
     * $xml = new Dom;
     *
     * $xml->fromArray(array(
     *     'foo' => 'bar'
     * ));
     *
     * echo $xml;
     * </code>
     * </pre>
     *
     * @return string
     */
    public function __toString()
    {
        return $this->saveXML();
    }

    /**
     * Save file to location
     *
     * @param string $path
     * @param int    $format Support xml, html, and json
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function save($path, $format = Document::XML)
    {
        switch ($format) {
            case Document::XML:
                $format = 'saveXML';
            break;
            case Document::HTML:
                $format = 'saveHTML';
            break;
            case Document::JSON:
                $format = 'toJson';
            break;
            default:
                throw new DomException('Invalid format', 2);
        }

        $tmp = Storage::temp($this->$format(), 'tmp', '~xml-');

        if ($tmp === false) {
            throw new DomException('Can\'t create tmp file', 2);
        } elseif (copy($tmp, $path) === false) {
            throw new DomException('Can\'t copy tmp file to ' . $path, 2);
        } else {
            unlink($tmp);
        }
    }

    /**
     * Get namespace attributes from element
     *
     * @param \DOMElement $element
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function getNamespaces(\DOMElement $element)
    {
        if ($this->xpath === null) {
            $this->xpath = new \DOMXPath($this);
        }

        $nodes = $this->xpath->query('namespace::*', $element);

        $ns = array();

        if ($nodes) {
            foreach ($nodes as $node) {
                $arr = $element->getAttribute($node->nodeName);

                if ($arr) {
                    $ns[$node->nodeName] = $arr;
                }
            }

            $nodes = null;
        }

        return $ns;
    }

    /**
     * Load XML from a string
     *
     * @param string $source
     * @param int    $options
     * @throws \Inphinit\Experimental\Dom\DomException
     * @return mixed
     */
    public function loadXML($source, $options = 0)
    {
        return $this->resource('loadXML', $source, $options);
    }

    /**
     * Load XML from a file
     *
     * @param string $filename
     * @param int    $options
     * @throws \Inphinit\Experimental\Dom\DomException
     * @return mixed
     */
    public function load($filename, $options = 0)
    {
        return $this->resource('load', $filename, $options);
    }

    /**
     * Load HTML from a string
     *
     * @param string $source
     * @param int    $options
     * @throws \Inphinit\Experimental\Dom\DomException
     * @return mixed
     */
    public function loadHTML($source, $options = 0)
    {
        return $this->resource('loadHTML', $source, $options);
    }

    /**
     * Load HTML from a file
     *
     * @param string $filename
     * @param int    $options
     * @throws \Inphinit\Experimental\Dom\DomException
     * @return mixed
     */
    public function loadHTMLFile($filename, $options = 0)
    {
        return $this->resource('loadHTMLFile', $filename, $options);
    }

    private function resource($function, $from, $options)
    {
        $this->enableRestoreInternal(true);

        $resource = parent::$function($from, $options);

        self::raise(4);

        $this->enableRestoreInternal(false);

        return $resource;
    }

    private function enableRestoreInternal($enable)
    {
        \libxml_clear_errors();

        if ($enable) {
            $this->internalErr = \libxml_use_internal_errors(true);
        } else {
            \libxml_use_internal_errors($this->internalErr);
        }
    }

    private function raise($level)
    {
        $err = \libxml_get_errors();

        if (isset($err[0])) {
            throw new DomException(null, $level);
        }
    }

    private function generate(\DOMNode $node, $data)
    {
        if (is_array($data) === false) {
            $node->textContent = $data;
            return;
        }

        foreach ($data as $key => $value) {
            if ($key === '@comments') {
                continue;
            } elseif ($key === '@contents') {
                $this->generate($node, $value);
            } elseif ($key === '@attributes') {
                $this->attrs($node, $value);
            } elseif (preg_match('#^([a-z]|[a-z][\w:])+$#i', $key)) {
                if (Helper::seq($value)) {
                    foreach ($value as $subvalue) {
                        $this->generate($node, array($key => $subvalue));
                    }
                } elseif (is_array($value)) {
                    $this->generate($this->add($key, '', $node), $value);
                } else {
                    $this->add($key, $value, $node);
                }
            }
        }
    }

    private function add($name, $value, \DOMNode $node)
    {
        $newdom = $this->createElement($name, $value);
        $node->appendChild($newdom);
        return $newdom;
    }

    private function attrs(\DOMNode $node, array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }
    }

    private function getNodes($nodes, $toplevel = false)
    {
        $items = array();

        if ($nodes) {
            foreach ($nodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE && ($this->complete || $this->simple || ctype_alnum($node->nodeName))) {
                    $items[$node->nodeName][] = $this->nodeContents($node);
                }
            }

            if (empty($items) === false) {
                self::simplify($items);
            }
        }

        return $items;
    }

    private function nodeContents(\DOMElement $node)
    {
        $extras = array( '@attributes' => array() );

        if ($this->complete) {
            foreach ($node->attributes as $attribute) {
                $extras['@attributes'][$attribute->nodeName] = $attribute->nodeValue;
            }
        }

        if ($this->complete && ($ns = $this->getNamespaces($node))) {
            $extras['@attributes'] = $extras['@attributes'] + $ns;
        }

        if ($node->getElementsByTagName('*')->length) {
            $r = $this->getNodes($node->childNodes) + (
                empty($extras['@attributes']) ? array() : $extras
            );
        } elseif (empty($extras['@attributes'])) {
            return $node->nodeValue;
        } else {
            $r = array($node->nodeValue) + $extras;
        }

        self::simplify($r);

        return $r;
    }

    private static function simplify(&$items)
    {
        if (self::toContents($items)) {
            foreach ($items as $name => &$item) {
                if (is_array($item) === false || strpos($name, '@') !== false) {
                    continue;
                }

                if (count($item) === 1 && isset($item[0])) {
                    $item = $item[0];
                } else {
                    self::toContents($item);
                }
            }
        }
    }

    private static function toContents(&$item)
    {
        if (count($item) > 1 && isset($item[0]) && isset($item[1]) === false) {
            $item['@contents'] = $item[0];
            unset($item[0]);

            return false;
        }

        return true;
    }
}
