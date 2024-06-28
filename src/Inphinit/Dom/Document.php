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

    private $format;
    private $loadOptions = 0;
    private $saveOptions = 0;

    private static $reporting;

    /**
     * Create a Document instance
     *
     * @param string $format
     */
    public function __construct($format = 0)
    {
        if (self::$reporting === null) {
            self::setReporting(self::FATAL);
        }

        $this->dom = new \DOMDocument();
        $this->format = $format;
    }

    /**
     * Define libXML errors as exceptions
     *
     * @param int $options
     * @return void
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
     * Define libXML options for load a document or string
     *
     * @param int $options
     * @return void
     */
    public function setLoadOptions($options)
    {
        $this->loadOptions = $options;
    }

    /**
     * Define libXML options for dump or save a document as file
     *
     * @param int $options
     * @return void
     */
    public function setSaveOptions($options)
    {
        $this->saveOptions = $options;
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

        $this->raise(3);

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
     * Load string or file
     *
     * @param string $source
     * @param bool   $file
     * @return void
     */
    public function load($source, $file = false)
    {
        if ($this->format === self::HTML) {
            $callback = array($this->dom, $file ? 'loadHTMLFile' : 'loadHTML');
        } elseif ($file) {
            $callback = array($this->dom, 'load');
        } else {
            $callback = array($this->dom, 'loadXML');
        }

        $this->enableInternalErrors(true);

        if ($this->loadOptions !== null) {
            $callback($source, $this->loadOptions);
        } else {
            $callback($source);
        }

        $this->raise(3);
    }

    /**
     * Convert document to XML string, HTML string or array
     *
     * @param \DOMNode $node
     * @return void
     */
    public function dump(\DOMNode $node = null)
    {
        if ($this->format === self::XML) {
            $callback = array($this->dom, 'saveXML');
            $options = $this->saveOptions;
        } else {
            $callback = array($this->dom, 'saveHTML');
            $options = 0;
        }

        if ($options !== 0) {
            return $callback($node, $options);
        }

        return $callback($node);
    }

    /**
     * Save document to file
     *
     * @param string $dest
     * @return void
     */
    public function save($dest)
    {
        if ($this->format === self::XML) {
            $callback = array($this->dom, 'save');
            $options = $this->saveOptions;
        } else {
            $callback = array($this->dom, 'saveHTMLFile');
            $options = 0;
        }

        if ($options !== 0) {
            return $callback($node, $options);
        }

        return $callback($node);
    }

    /**
     * Convert Array to DOM
     *
     * @param array $data
     */
    public function fromArray(array $data)
    {
        if (empty($data)) {
            throw new Exception('Array is empty', 0, 3);
        } elseif (count($data) > 1) {
            throw new Exception('Root array accepts only a key', 0, 3);
        }

        $root = key($data);

        if ($this->format === self::HTML && strcasecmp($root, 'html') !== 0) {
            throw new Exception('HTML except <' . $root . '> tag as root');
        } elseif ($this->format === self::XML && self::validTag($root) === false) {
            throw new Exception('Invalid <' . $root . '> root tag');
        }

        $dom = $this->dom;

        if ($this->format === self::HTML) {
            $dom->loadHTML('<!DOCTYPE html><html></html>');
        }

        if ($dom->documentElement) {
            $dom->removeChild($dom->documentElement);
        }

        $this->enableInternalErrors(true);

        $this->generate($dom, $data, 3);

        $this->raise(0);
    }

    /**
     * Convert DOM to Array
     *
     * @param int     $format
     * @param DOMNode $node
     */
    public function toArray($format, \DOMNode $node = null)
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
            if ($this->dom->documentElement->contains($node) === false) {
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
        $exception = null;

        foreach (\libxml_get_errors() as $error) {
            if ($error->level === \LIBXML_ERR_WARNING) {
                $reported = self::WARNING;
            } elseif ($error->level === \LIBXML_ERR_ERROR) {
                $reported = self::ERROR;
            } else {
                $reported = self::FATAL;
            }

            if (self::$reporting & $reported) {
                $exception = new DomException(null, $level);
            }

            if ($exception !== null) {
                $this->enableInternalErrors(false);
                throw $exception;
            }
        }

        \libxml_clear_errors();

        $this->enableInternalErrors(false);
    }

    private function generate(\DOMNode $node, &$data, $errorLevel)
    {
        if (is_array($data) === false) {
            $node->nodeValue = $data;
            return;
        }

        ++$errorLevel;

        foreach ($data as $key => $value) {
            if ($key === '@comments') {
                continue;
            } elseif ($key === '@contents') {
                $this->generate($node, $value, $errorLevel);
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
                        $this->generate($node, $create, $errorLevel);
                    }
                } else {
                    $this->generate($this->add($key, '', $node), $value, $errorLevel);
                }
            } else {
                throw new Exception('Invalid tag: <' . $key . '>', 0, $errorLevel);
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
