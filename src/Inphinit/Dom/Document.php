<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Dom;

use Inphinit\Storage;
use Inphinit\Utility\Arrays;

class Document
{
    /** Used with `Document::toArray` method to convert document in a simple array */
    const SIMPLE = 1;

    /** Used with `Document::toArray` method to convert document in a minimal array */
    const MINIMAL = 2;

    /** Used with `Document::toArray` method to convert document in a array with all properties */
    const COMPLETE = 3;

    /**  */
    const ERROR = 4;
    const FATAL = 8;
    const WARNING = 16;

    private $dom;
    private $xpath;
    private $selector;

    private $internalErrors;
    private $exceptionLevel = 3;

    private $complete = false;
    private $simple = false;

    private static $reporting;

    /**
     * Create a Document instance
     *
     * @param string $version  The version number of the document as part of the XML declaration
     * @param string $encoding The encoding of the document as part of the XML declaration
     * @return void
     */
    public function __construct($version = '1.0', $encoding = 'UTF-8')
    {
        if (self::$reporting === null) {
            self::$reporting = self::ERROR | self::FATAL;
        }

        $this->dom = new \DOMDocument($version, $encoding);
    }

    public static function setReporting($options)
    {
        $types = self::ERROR | self::FATAL | self::WARNING;

        if (!($types & $reporting)) {
            throw new Inphinit\Exception('Invalid reporting');
        }

        self::$reporting = $options;
    }

    /**
     * Convert a array in node elements
     *
     * @param array|\Traversable $data
     * @throws \Inphinit\Dom\DomException
     * @return void
     */
    public function fromArray(array &$data)
    {
        if (empty($data)) {
            throw new DomException('Array is empty', 0, 2);
        } elseif (count($data) > 1) {
            throw new DomException('Root array accepts only a key', 0, 2);
        }

        $root = key($data);

        if (self::validTag($root) === false) {
            throw new DomException('Invalid root <' . $root . '> tag', 0, 2);
        }

        if ($this->dom->documentElement) {
            $this->removeChild($this->dom->documentElement);
        }

        $this->enableInternalErrors(true);

        $this->generate($this, $data, 2);

        $this->raise($this->exceptionLevel);

        $this->enableInternalErrors(false);
    }

    /**
     * Convert DOM to Array
     *
     * @param int $type
     * @throws \Inphinit\Dom\DomException
     * @return array
     */
    public function toArray($type = Document::SIMPLE)
    {
        switch ($type) {
            case self::MINIMAL:
                $this->simple = false;
                $this->complete = false;
                break;

            case self::SIMPLE:
                $this->simple = true;
                break;

            case self::COMPLETE:
                $this->complete = true;
                break;

            default:
                throw new DomException('Invalid type', 2);
        }

        return $this->getNodes($this->dom->childNodes);
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
     * Get namespace attributes from root element or specific element
     *
     * @param \DOMElement $element
     * @return void
     */
    public function getNamespaces(\DOMElement $element = null)
    {
        if ($this->xpath === null) {
            $this->xpath = new \DOMXPath($this->dom);
        }

        if ($element === null) {
            $nodes = $this->xpath->query('namespace::*');
            $element = $this->dom->documentElement;
        } else {
            $nodes = $this->xpath->query('namespace::*', $element);
        }

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

    // LOAD XML from FILE
    public function load($filename, $options = 0)
    {
        return $this->callMethod('load', $filename, $options);
    }

    // LOAD XML from STRING
    public function loadXML($source, $options = 0)
    {
        return $this->callMethod('loadXML', $source, $options);
    }

    // LOAD HTML from FILE
    public function loadHTMLFile($filename, $options = 0)
    {
        return $this->callMethod('loadHTMLFile', $filename, $options);
    }

    // LOAD HTML from STRING
    public function loadHTML($source, $options = 0)
    {
        return $this->callMethod('loadHTML', $source, $options);
    }

    // SAVE HTML to file
    public function saveHTMLFile($filename)
    {
        return $this->callMethod('saveHTMLFile', $filename, null);
    }

    // SAVE HTML to STRING
    public function saveHTML(\DOMNode $node = null)
    {
        return $this->callMethod('saveHTML', $node, null);
    }

    // SAVE HTML to FILE
    public function save($filename, int $options = 0)
    {
        return $this->callMethod('save', $filename, $options);
    }

    /**
     * Save HTML to string
     *
     * @param DOMNode $node
     * @param int     $options
     * @throws \Inphinit\Dom\DomException
     * @return string|false
     */
    public function saveXML(\DOMNode $node = null, $options = 0)
    {
        return $this->callMethod('saveXML', $node, $options);
    }

    /**
     * Use query-selector like CSS, jQuery, querySelectorAll
     *
     * @param string   $selector
     * @param \DOMNode $context
     * @return \DOMNodeList
     */
    public function query($selector, \DOMNode $context = null)
    {
        $this->enableInternalErrors(true);

        if ($this->selector === null) {
            $this->selector = new Selector($this->dom);
        }

        $nodes = $this->selector->get($selector, $context);

        $level = $this->exceptionLevel;

        $this->exceptionLevel = 3;

        $this->raise($level);

        $this->enableInternalErrors(false);

        return $nodes;
    }

    /**
     * Use query-selector like CSS, jQuery, querySelector
     *
     * @param string   $selector
     * @param \DOMNode $context
     * @return \DOMNode
     */
    public function first($selector, \DOMNode $context = null)
    {
        $this->exceptionLevel = 4;

        $nodes = $this->query($selector, $context);

        $node = $nodes ? $nodes->item(0) : null;

        $nodes = null;

        return $node;
    }

    private function callMethod($function, $param, $options)
    {
        $this->enableInternalErrors(true);

        $callback = array($this->dom, $function);

        if ($options !== null) {
            $resource = $callback($param, $options);
        } else {
            $resource = $callback($param);
        }

        $callback = null;

        $this->raise(4);

        $this->enableInternalErrors(false);

        return $resource;
    }

    private function enableInternalErrors($enable)
    {
        \libxml_clear_errors();

        if ($enable) {
            $this->internalErrors = \libxml_use_internal_errors(true);
        } else {
            \libxml_use_internal_errors($this->internalErrors);
        }
    }

    private function raise($level)
    {
        foreach (\libxml_get_errors() as $error) {
            if ($error->level & $this->reporting) {
                throw new DomException(null, $level);
            }
        }

        \libxml_clear_errors();
    }

    private function generate(\DOMNode $node, &$data, $errorLevel)
    {
        if (is_array($data) === false) {
            $node->textContent = $data;
            return;
        }

        $nextLevel = $errorLevel + 1;

        foreach ($data as $key => $value) {
            if ($key === '@comments') {
                continue;
            } elseif ($key === '@contents') {
                $this->generate($node, $value, $nextLevel);
            } elseif ($key === '@attributes') {
                foreach ($attributes as $name => $value) {
                    $node->setAttribute($name, $value);
                }
            } elseif (self::validTag($key)) {
                if (Arrays::indexed($value)) {
                    foreach ($value as $subvalue) {
                        $create = array($key => $subvalue);
                        $this->generate($node, $create, $nextLevel);
                    }
                } elseif (is_array($value)) {
                    $this->generate($this->add($key, '', $node), $value, $nextLevel);
                } else {
                    $this->add($key, $value, $node);
                }
            } else {
                throw new DomException('Invalid tag: <' . $key . '>', 0, $nextLevel);
            }
        }
    }

    private static function validTag($tagName)
    {
        return preg_match('#^([a-z_](\w+|)|[a-z_](\w+|):[a-z_](\w+|))$#i', $tagName) > 0;
    }

    private function add($name, $value, \DOMNode $node)
    {
        $created = $this->dom->createElement($name, $value);
        $node->appendChild($created);
        return $newdom;
    }

    private function getNodes($nodes)
    {
        $items = array();

        if ($nodes) {
            foreach ($nodes as $node) {
                if ($node->nodeType === \XML_ELEMENT_NODE && ($this->complete || $this->simple || ctype_alnum($node->nodeName))) {
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
        $extras = array('@attributes' => array());

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
