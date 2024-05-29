<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Dom;

use Inphinit\Helper;
use Inphinit\Storage;

class Document extends \DOMDocument
{
    private $xpath;
    private $selector;

    private $internalErr;
    private $exceptionlevel = 3;

    private $complete = false;
    private $simple = false;

    /**
     * Used with `Document::reporting` method or in extended classes
     *
     * @var array
     */
    protected $levels = array(\LIBXML_ERR_WARNING, \LIBXML_ERR_ERROR, \LIBXML_ERR_FATAL);

    /** Used with `Document::save` method to save document in XML format */
    const XML = 1;

    /** Used with `Document::save` method to save document in HTML format */
    const HTML = 2;

    /** Used with `Document::save` method to convert and save document in JSON format */
    const JSON = 3;

    /** Used with `Document::toArray` method to convert document in a simple array */
    const SIMPLE = 4;

    /** Used with `Document::toArray` method to convert document in a minimal array */
    const MINIMAL = 5;

    /** Used with `Document::toArray` method to convert document in a array with all properties */
    const COMPLETE = 6;

    /**
     * Create a Document instance
     *
     * @param string $version  The version number of the document as part of the XML declaration
     * @param string $encoding The encoding of the document as part of the XML declaration
     * @return void
     */
    public function __construct($version = '1.0', $encoding = 'UTF-8')
    {
        parent::__construct($version, $encoding);
    }

    /**
     * Set level error for exception, set `LIBXML_ERR_NONE` (or `0` - zero) for disable exceptions.
     * For disable only warnings use like this `$dom->reporting(LIBXML_ERR_FATAL, LIBXML_ERR_ERROR)`
     *
     * <ul>
     * <li>0 - `LIBXML_ERR_NONE` - Disable errors</li>
     * <li>1 - `LIBXML_ERR_WARNING` - Show warnings in DOM</li>
     * <li>2 - `LIBXML_ERR_ERROR` - Show recoverable errors in DOM</li>
     * <li>3 - `LIBXML_ERR_FATAL` - Show DOM fatal errors</li>
     * </ul>
     *
     * @param int $args,...
     * @return void
     */
    public function reporting()
    {
        $this->levels = func_get_args();
    }

    /**
     * Convert a array in node elements
     *
     * @param array|\Traversable $data
     * @throws \Inphinit\Dom\DomException
     * @return void
     */
    public function fromArray(array $data)
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

        if ($this->documentElement) {
            $this->removeChild($this->documentElement);
        }

        $this->enableRestoreInternal(true);

        $this->generate($this, $data, 2);

        $this->raise($this->exceptionlevel);

        $this->enableRestoreInternal(false);
    }

    /**
     * Convert DOM to JSON string
     *
     * @param bool $format
     * @param int  $options `JSON_HEX_QUOT`, `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_NUMERIC_CHECK`, `JSON_PRETTY_PRINT`, `JSON_UNESCAPED_SLASHES`, `JSON_FORCE_OBJECT`, `JSON_PRESERVE_ZERO_FRACTION`, `JSON_UNESCAPED_UNICODE`, `JSON_PARTIAL_OUTPUT_ON_ERROR`. The behaviour of these constants is described in http://php.net/manual/en/json.constants.php
     *
     * @return string
     */
    public function toJson($format = Document::MINIMAL, $options = 0)
    {
        $this->exceptionlevel = 4;

        $json = json_encode($this->toArray($format), $options);

        $this->exceptionlevel = 3;

        return $json;
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
            case Document::MINIMAL:
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

        return $this->getNodes($this->childNodes);
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
     * @param int    $format Support XML, HTML, and JSON
     * @throws \Inphinit\Dom\DomException
     * @return void
     */
    #[\ReturnTypeWillChange]
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

        if (Storage::createFolder('tmp/dom')) {
            $tmp = Storage::temp($this->$format(), 'tmp/dom');
        } else {
            $tmp = false;
        }

        if ($tmp === false) {
            throw new DomException('Can\'t create tmp file', 2);
        } elseif (copy($tmp, $path) === false) {
            throw new DomException('Can\'t copy tmp file to ' . $path, 2);
        } else {
            unlink($tmp);
        }
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
            $this->xpath = new \DOMXPath($this);
        }

        if ($element === null) {
            $nodes = $this->xpath->query('namespace::*');
            $element = $this->documentElement;
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
     * Load XML from a string
     *
     * @param string $source
     * @param int    $options
     * @throws \Inphinit\Dom\DomException
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
     * @throws \Inphinit\Dom\DomException
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
     * @throws \Inphinit\Dom\DomException
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
     * @throws \Inphinit\Dom\DomException
     * @return mixed
     */
    public function loadHTMLFile($filename, $options = 0)
    {
        return $this->resource('loadHTMLFile', $filename, $options);
    }

    /**
     * Use query-selector like CSS, jQuery, querySelectorAll
     *
     * @param string $selector
     * @param \DOMNode $context
     * @return \DOMNodeList
     */
    public function query($selector, \DOMNode $context = null)
    {
        $this->enableRestoreInternal(true);

        if ($this->selector === null) {
            $this->selector = new Selector($this);
        }

        $nodes = $this->selector->get($selector, $context);

        $level = $this->exceptionlevel;

        $this->exceptionlevel = 3;

        $this->raise($level);

        $this->enableRestoreInternal(false);

        return $nodes;
    }

    /**
     * Use query-selector like CSS, jQuery, querySelector
     *
     * @param string $selector
     * @param \DOMNode $context
     * @return \DOMNode
     */
    public function first($selector, \DOMNode $context = null)
    {
        $this->exceptionlevel = 4;

        $nodes = $this->query($selector, $context);

        $node = $nodes ? $nodes->item(0) : null;

        $nodes = null;

        return $node;
    }

    private function resource($function, $from, $options)
    {
        $this->enableRestoreInternal(true);

        $resource = PHP_VERSION_ID >= 50400 ? parent::$function($from, $options) : parent::$function($from);

        $this->raise(4);

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

    private function raise($debuglvl)
    {
        $err = \libxml_get_errors();

        if (isset($err[0]->level) && in_array($err[0]->level, $this->levels, true)) {
            throw new DomException(null, $debuglvl);
        }

        \libxml_clear_errors();
    }

    private function generate(\DOMNode $node, $data, $errorLevel)
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
                self::setAttributes($node, $value);
            } elseif (self::validTag($key)) {
                if (Helper::seq($value)) {
                    foreach ($value as $subvalue) {
                        $this->generate($node, array($key => $subvalue), $nextLevel);
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
        $newdom = $this->createElement($name, $value);
        $node->appendChild($newdom);
        return $newdom;
    }

    private static function setAttributes(\DOMNode $node, array &$attributes)
    {
        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }
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
