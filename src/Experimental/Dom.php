<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Helper;
use Inphinit\Storage;

class Dom extends \DOMDocument
{
    private $xpath;
    private $logerrors = array();
    private $internalErr;

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

    public function __destruct()
    {
        $this->logerrors = null;
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
        if (count($data) > 1) {
            throw new Exception('Root array accepts only a key', 2);
        } elseif (count($data) === 1 && Helper::seq($data[key($data)])) {
            throw new Exception('Document accpet only a node', 2);
        }

        if ($this->documentElement) {
            $this->removeChild($this->documentElement);
        }

        $this->enableInternal(true);

        $this->generate($this, $data);

        $this->raise();

        $this->enableInternal(false);
    }

    /**
     * Convert Dom to json string
     *
     * @param bool $format
     * @param int $options `JSON_HEX_QUOT`, `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_NUMERIC_CHECK`, `JSON_PRETTY_PRINT`, `JSON_UNESCAPED_SLASHES`, `JSON_FORCE_OBJECT`, `JSON_PRESERVE_ZERO_FRACTION`, `JSON_UNESCAPED_UNICODE`, `JSON_PARTIAL_OUTPUT_ON_ERROR`. The behaviour of these constants is described in http://php.net/manual/en/json.constants.php
     *
     * @return string
     */
    public function toJson($format = Dom::MININAL, $options = 0)
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
    public function toArray($type = Dom::SIMPLE)
    {
        switch ($type) {
            case Dom::MININAL:
                $this->simple = false;
                $this->complete = false;
            break;
            case Dom::SIMPLE:
                $this->simple = true;
            break;
            case Dom::COMPLETE:
                $this->complete = true;
            break;
            default:
                throw new Exception('Invalid type', 2);
        }

        return $this->getNodes($this->childNodes, true);
    }

    /**
     * Save internal errors from libxml
     *
     * @return void
     */
    protected static function raise()
    {
        $err = \libxml_get_errors();

        if (isset($err[0])) {
            if (isset($err[0]->file) && !preg_match('#https?://#', $err[0]->file)) {
                throw new DomException;
            } else {
                throw new Exception($err[0]->message, 3);
            }
        }
    }

    /**
     * Get internal errors from libxml
     *
     * @return mixed
     */
    public function loadXML($source, $options = 0)
    {
        $this->enableInternal(true);

        $r = parent::loadXML($source, $options);

        self::raise();

        $this->enableInternal(false);

        return $r;
    }

    /**
     *
     * @return bool
     */
    public function load($filename, $options = 0)
    {
        $this->enableInternal(true);

        $r = parent::load($filename, $options);

        self::raise();

        $this->enableInternal(false);

        return $r;
    }

    /**
     * Get internal errors from libxml
     *
     * @return bool
     */
    public function loadHTML($source, $options = 0)
    {
        $this->enableInternal(true);

        $r = parent::loadHTML($source, $options);

        self::raise();

        $this->enableInternal(false);

        return $r;
    }

    /**
     * Get internal errors from libxml
     *
     * @return bool
     */
    public function loadHTMLFile($filename, $options = 0)
    {
        $this->enableInternal(true);

        $r = parent::loadHTMLFile($filename, $options);

        self::raise();

        $this->enableInternal(false);

        return $r;
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
    public function save($path, $format = Dom::XML)
    {
        switch ($format) {
            case Dom::XML:
                $format = 'saveXML';
            break;
            case Dom::HTML:
                $format = 'saveHTML';
            break;
            case Dom::JSON:
                $format = 'toJson';
            break;
            default:
                throw new Exception('Invalid format', 2);
            break;
        }

        $tmp = Storage::temp($this->$format(), 'tmp', '~xml-');

        if ($tmp === false) {
            throw new Exception('Can\'t create tmp file', 2);
        } elseif (copy($tmp, $path) === false) {
            throw new Exception('Cannot copy tmp file to ' . $path, 2);
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
        }

        return $ns;
    }

    private function enableInternal($enable)
    {
        if ($enable) {
            $this->internalErr = \libxml_use_internal_errors(true);
            \libxml_clear_errors();
        } else {
            \libxml_clear_errors();
            \libxml_use_internal_errors($this->internalErr);
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
        if ($nodes) {
            $items = array();

            foreach ($nodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE && ($this->complete || $this->simple || ctype_alnum($node->nodeName))) {
                    $items[$node->nodeName][] = $this->nodeContents($node);
                }
            }

            if (empty($items) === false) {
                self::simplify($items);
                return $items;
            }
        }
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
