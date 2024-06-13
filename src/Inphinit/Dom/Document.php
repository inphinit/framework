<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Dom;

use Inphinit\Exception;
use Inphinit\Storage;
use Inphinit\Utility\Arrays;

class Document
{
    /** Used with `Document::dump` or `Document::save` method to convert document in a simple array */
    const ARRAY_SIMPLE = 1;

    /** Used with `Document::dump` or `Document::save` method to convert document in a minimal array */
    const ARRAY_MINIMAL = 2;

    /** Used with `Document::dump` or `Document::save` method to convert document in a array with all properties */
    const ARRAY_COMPLETE = 3;

    /**  */
    const HTML = 4;
    const XML = 5;
    const HTML_FILE = 6;
    const XML_FILE = 7;

    /** Constants to treat errors issued by libXML as exceptions */
    const ERROR = 16;
    const FATAL = 32;
    const WARNING = 64;

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
    public function __construct($source, $format = 0, $options = 0)
    {
        if (self::$reporting === null) {
            self::setReporting(0);
        }

        $this->dom = new \DOMDocument;

        switch ($format) {
            case self::XML_FILE:
                $this->load($source, $options, 'load', false);
                break;

            case self::XML:
                $this->load($source, $options, 'loadXML', true);
                break;

            case self::HTML:
                $this->load($source, $options, 'loadHTML', false);
                break;

            case self::HTML_FILE:
                $this->load($source, $options, 'loadHTMLFile', true);
                break;

            default:
                if (is_array($source)) {
                    $this->fromArray($source);
                } else {
                    throw new Exception('Invalido or undefined format param');
                }
        }
    }

    /**
     * Define libXML errors as exceptions
     *
     * @param int $options
     */
    public static function setReporting($options)
    {
        $types = self::ERROR | self::FATAL | self::WARNING;

        if ($options && !($types & $options)) {
            throw new Exception('Invalid reporting options');
        }

        self::$reporting = $options;
    }

    /**
     * Gets all elements that matches the CSS selector (like document.querySelectorAll)
     *
     * @param string $selector
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
     * Gets an element that matches the CSS selector (like document.querySelector)
     *
     * @param string $selector
     * @param \DOMNode $context
     * @return \DOMNode
     */
    public function first($selector, \DOMNode $context = null)
    {
        $this->exceptionLevel = 4;

        $node = null;
        $nodes = $this->query($selector, $context);

        if ($nodes) {
            $node = $nodes->item(0);
        }

        return $node;
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

    /**
     * Convert document to XML string, HTML string or array
     *
     * @param int         $format
     * @param \DOMElement $element
     * @param int         $options
     * @return void
     */
    public function dump($format, \DOMNode $node = null, $options = 0)
    {
        switch ($format) {
            case self::HTML:
                return $this->dom->saveHTML($node, $options);

            case self::XML:
                return $this->dom->saveXML($node, $options);

            case self::ARRAY_COMPLETE:
            case self::ARRAY_MINIMAL:
            case self::ARRAY_SIMPLE:
                return $this->toArray($format, $node);

            default:
                throw new Exception('Invalid format param');
        }
    }

    /**
     * Save document to file
     *
     * @param string $filename
     * @param int    $format
     * @param int    $options
     * @return void
     */
    public function save($filename, $format, $options = 0)
    {
        switch ($format) {
            case self::HTML:
                $this->dom->saveHTMLFile($node, $options);
                break;

            case self::XML:
                $this->dom->save($node, $options);
                break;

            default:
                throw new Exception('Invalid format param');
        }
    }

    /**
     * Get original document
     *
     * @return \DOMDocument
     */
    public function document()
    {
        return $this->dom;
    }

    private function fromArray($data)
    {
        if (empty($data)) {
            throw new Exception('Array is empty', 0, 3);
        } elseif (count($data) > 1) {
            throw new Exception('Root array accepts only a key', 0, 3);
        }

        $root = key($data);

        if (self::validTag($root) === false) {
            throw new Exception('Invalid root <' . $root . '> tag', 0, 3);
        }

        if ($this->dom->documentElement) {
            $this->dom->removeChild($this->dom->documentElement);
        }

        $this->enableInternalErrors(true);

        $this->generate($this->dom, $data, 3);

        $this->raise($this->exceptionLevel);

        $this->enableInternalErrors(false);
    }

    private function load($source, $options, $callback, $isFile)
    {
        $this->enableInternalErrors(true);

        $this->dom->{$callback}($source, $options);

        $this->raise(3);

        $this->enableInternalErrors(false);
    }

    private function toArray($format, $node)
    {
        switch ($format) {
            case self::ARRAY_MINIMAL:
                $this->simple = false;
                $this->complete = false;
                break;

            case self::ARRAY_SIMPLE:
                $this->simple = true;
                break;

            default:
                $this->complete = true;
        }

        if ($node) {
            if ($dom->documentElement->contains($node) === false) {
                return false;
            }

            return $this->getNodes($node->childNodes);
        }

        return $this->getNodes($this->dom->childNodes);
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
            if ($error->level === \LIBXML_ERR_WARNING) {
                $reported = self::WARNING;
            } elseif ($error->level === \LIBXML_ERR_ERROR) {
                $reported = self::ERROR;
            } else {
                $reported = self::FATAL;
            }

            if (self::$reporting & $reported) {
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
                foreach ($value as $subKey => $subValue) {
                    $node->setAttribute($subKey, $subValue);
                }
            } elseif (self::validTag($key)) {
                if (is_array($value) === false) {
                    $this->add($key, $value, $node);
                } elseif (Arrays::indexed($value)) {
                    foreach ($value as $subvalue) {
                        $create = array($key => $subvalue);
                        $this->generate($node, $create, $nextLevel);
                    }
                } else {
                    $this->generate($this->add($key, '', $node), $value, $nextLevel);
                }
            } else {
                throw new Exception('Invalid tag: <' . $key . '>', 0, $nextLevel);
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
        return $created;
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
