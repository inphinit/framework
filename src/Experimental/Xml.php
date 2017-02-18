<?php
/*
 * Inphinit
 *
 * Copyright (c) 2017 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\Storage;

class Xml
{
    private $handle;
    private $logerrors = array();

    /**
     * Create a `Experimental\Xml` instance.
     *
     * @param string $root
     * @param string $charset
     * @return void
     */
    public function __construct($root, $charset = 'UTF-8')
    {
        if (empty($root) || ctype_alpha($root) === false) {
            throw new Exception('First argument in Response::xml requires a string', 2);
            return false;
        }

        $default  = '<?xml version="1.0"';

        if (is_string($charset)) {
            $default .= ' encoding="' . $charset . '"';
        }

        $default .= '?><' . $root . '></' . $root . '>';

        $this->handle = new \SimpleXMLElement($default);
    }

    /**
     * Convert array in xml tags
     *
     * @param array $data
     * @return void
     */
    public function fromArray(array $data)
    {
        $restore = \libxml_use_internal_errors(true);

        self::generate($data, $this->handle);
        $this->saveErrors();

        \libxml_use_internal_errors($restore);
    }

    /**
     * Save internal errors from libxml
     *
     * @return void
     */
    protected function saveErrors()
    {
        foreach (libxml_get_errors() as $error) {
            if (in_array($error, $this->errors)) {
                $this->logerrors[] = $error;
            }
        }
    }

    /**
     * Get internal errors from libxml
     *
     * @return void
     */
    public function errors()
    {
        return $this->logerrors;
    }

    /**
     * Magic method, return a well-formed XML string
     *
     * Example:
     * <pre>
     * <code>
     * $xml = new Xml('root');
     *
     * $xml->fromArray(array(
     *     'foo' => 'bar'
     * ));
     *
     * echo $xml;
     * </code>
     * </pre>
     *
     * @throws \Inphinit\Experimental\Exception
     * @return string
     */
    public function __toString()
    {
        if ($this->handle) {
            return $this->handle->asXML();
        }

        throw new Exception('XML was not generated because the handle is no longer available.', 2);
    }

    /**
     * Save file to location
     *
     * @param string $path
     * @throws \Inphinit\Experimental\Exception
     * @return string
     */
    public function save($path)
    {
        if (false !== is_writable(dirname($path))) {
            throw new Exception('Path is not writable', 2);
        }

        if ($this->handle) {
            $tmp = Storage::temp($this->handle->asXML(), 'tmp', '~xml-');
            $response = copy($tmp, $path);

            unlink($tmp);

            return $response;
        }

        throw new Exception('XML was not generated because the handle is no longer available.', 2);
    }

    /**
     * Recursively iterates the items in an array to convert to XML elements.
     *
     * @param string $data
     * @param \SimpleXMLElement $xmlNode
     * @return void
     */
    private static function generate($data, \SimpleXMLElement $xmlNode)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    continue;
                }

                self::generate($value, $xmlNode->addChild($key));
            } elseif (empty($key) === false && is_numeric($key) === false) {
                $xmlNode->addChild($key, htmlspecialchars($value));
            }
        }

        $data = $xmlNode = null;
    }

    public function __destruct()
    {
        $this->handle = $this->logerrors = null;
    }
}
