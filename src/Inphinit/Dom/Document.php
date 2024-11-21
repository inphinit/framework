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

    private $base;
    private $xpath;
    private $selector;

    private $internalErrors;

    private $complete = false;
    private $simple = false;

    private $type;
    private $loadOptions = 0;
    private $saveOptions = 0;

    private static $severityLevels;

    /**
     * Create a Document instance
     *
     * @param string $type
     */
    public function __construct($type = 0)
    {
        if ($type !== self::HTML && $type !== self::XML) {
            throw new Exception('Invalid format');
        }

        if (self::$severityLevels === null) {
            self::setSeverityLevels(self::FATAL);
        }

        $this->type = $type;

        $this->base = new \DOMDocument();
    }

    /**
     * Define libXML errors as exceptions
     *
     * @param int $options
     * @return void
     */
    public static function setSeverityLevels($options)
    {
        $levels = self::ERROR | self::FATAL | self::WARNING;

        if ($options !== 0 && !($levels & $options)) {
            throw new Exception('Invalid reporting options');
        }

        self::$severityLevels = $options;
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
        return $this->base;
    }

    /**
     * Get root element
     *
     * @return \DOMDocument
     */
    public function root()
    {
        return $this->base->documentElement;
    }

    /**
     * Gets all elements that match the CSS selector
     *
     * @return \Inphinit\Dom\Selector
     */
    public function selector()
    {
        $this->enableInternalErrors(true);

        if ($this->selector === null) {
            $this->selector = new Selector($this->base);
        }

        return $this->selector;
    }

    /**
     * Get namespace attributes from root element or specific element
     *
     * @param \DOMElement $element
     * @return void
     */
    public function getNamespaces(\DOMElement $element)
    {
        if ($this->xpath === null) {
            $this->xpath = new \DOMXPath($this->base);
        }

        if ($element === $this->base->documentElement) {
            $nodes = $this->xpath->query('namespace::*');
        } else {
            $nodes = $this->xpath->query('namespace::*', $element);
        }

        $items = array();

        if ($nodes) {
            foreach ($nodes as $node) {
                $arr = $element->getAttribute($node->nodeName);

                if ($arr) {
                    $items[$node->nodeName] = $arr;
                }
            }

            $nodes = null;
        }

        return $items;
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
        if ($this->type === self::HTML) {
            $callback = array($this->base, $file ? 'loadHTMLFile' : 'loadHTML');
        } elseif ($file) {
            $callback = array($this->base, 'load');
        } else {
            $callback = array($this->base, 'loadXML');
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
    public function dump(\DOMNode $node)
    {
        if ($this->type === self::XML) {
            $callback = array($this->base, 'saveXML');
            $options = $this->saveOptions;
        } else {
            $callback = array($this->base, 'saveHTML');
            $options = 0;
        }

        return $options === 0 ? $callback($node) : $callback($node, $options);
    }

    /**
     * Save document to file
     *
     * @param string $file
     * @return void
     */
    public function save($file)
    {
        if ($this->type === self::XML) {
            $callback = array($this->base, 'save');
            $options = $this->saveOptions;
        } else {
            $callback = array($this->base, 'saveHTMLFile');
            $options = 0;
        }

        return $options === 0 ? $callback($file) : $callback($file, $options);
    }

    /**
     * Convert Array to DOM
     *
     * @param array $data
     * @return void
     */
    public function fromArray(array $data)
    {
        if (empty($data)) {
            throw new Exception('Array is empty');
        } elseif (count($data) > 1) {
            throw new Exception('Root array accepts only a key');
        }

        $root = key($data);

        if ($this->type === self::HTML && strcasecmp($root, 'html') !== 0) {
            throw new Exception('Document::HTML expects "html" key as root');
        } elseif ($this->type === self::XML && self::validTag($root) === false) {
            throw new Exception('Invalid "' . $root . '" key as root');
        }

        $dom = $this->base;

        if ($this->type === self::HTML) {
            $dom->loadHTML('<!DOCTYPE html><html></html>');
        }

        if ($dom->documentElement) {
            $dom->removeChild($dom->documentElement);
        }

        $this->enableInternalErrors(true);

        $this->generate($dom, $data, 3);

        $this->raise(3);
    }

    /**
     * Convert DOM to Array
     *
     * @param DOMNode $node
     * @param int     $format
     * @return array
     */
    public function toArray(\DOMNode $node, $format)
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
            if ($this->base->documentElement->contains($node) === false) {
                return false;
            }

            return $this->getNodes($node->childNodes);
        }

        return $this->getNodes($this->base->childNodes);
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

            if (self::$severityLevels & $reported) {
                $exception = new DomException($error, $level);
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
            $created = $this->base->createTextNode($data);
            $node->appendChild($created);
            return;
        }

        ++$errorLevel;

        foreach ($data as $key => $value) {
            if ($key === '@comment') {
                $created = $this->base->createComment((string) $value);
                $node->appendChild($created);
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
        $created = $this->base->createElement($name, $value);
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
            $contents = $this->getNodes($node->childNodes) + (
                empty($extras['@attributes']) ? array() : $extras
            );
        } elseif (empty($extras['@attributes'])) {
            return $node->nodeValue;
        } else {
            $contents = array($node->nodeValue) + $extras;
        }

        self::simplify($contents);

        return $contents;
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
