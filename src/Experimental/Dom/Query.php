<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Dom;

class Query extends \DOMXPath
{
    private $prevent;
    private $restore;
    private $rules;
    private $qxs = array(
        array( '/^([^a-z*])/', '*\\1' ),
        array( '/([>\+~])([^a-z])/', '\\1*\\2' ),
        array( '/\[(.*?)\]/', '[@\\1]' ),
        array( '/#(\w+)/', '[@id="\\1"]' ),
        array( '/\:empty/', '[count(*)=0]'),
        array( '/\:last-child/', '[last()]' ),
        array( '/^(.*?)\:first-child $/', 'descendant::\\1' ),
        array( '/\:nth-child\(n\)/', '[position() mod n = 1]' ),
        array( '/\:nth-child\(odd\)/', '[(position() mod 2)=1]' ),
        array( '/\:nth-child\(even\)/', '[(position() mod 2)=0]' ),
        array( '/\:nth-child\((\d+)n\)/', '[(position() mod \\1)=0]' ),
        array( '/\:nth-child\((\d+)n\+(\d+)\)/', '[(position() mod \\1)=\\2]' ),
        array( '/\:lang\(([\w\-]+)\)/', '[starts-with(concat(@lang,"-"),concat("\\1","-"))]' ),
        array( '/\[(@\w+)\^=([^"\'])(.*?)\\1\]/', '[starts-with(\\1,\\2\\3\\2)]' ),
        array( '/\[(@\w+)\*=([^"\'])(.*?)\\1\]/', '[contains(\\1,\\2\\3\\2)]' ),
        array( '/\[(@\w+)\~=([^"\'])(.*?)\\1\]/', '[contains(concat(" ",\\1," "),\\2 \\3 \\2)]' ),
        array( '/\[(@\w+)\|=([^"\'])(.*?)\\1\]/', '[starts-with(concat(\\1,"-"),concat(\\2\\3\\2,"-"))]' ),
        array( '/\[(@\w+)\$=([^"\'])(.*?)\\1\]/', '[substring(\\1,string-length(\\1)-2)=\\2\\3\\2]' ),
        array( '/\.(\w+)/', '[contains(concat(" ",@class," ")," \\1 ")]' ),
        array( '/\:contains\((.*?)\)/', '[contains(text(),\\1)]' ),
        array( '/\+/', '/following-sibling::' ),
        array( '/[~]([*\w]{1,})/', '/following-sibling::*[count(\\1)]' ),
        array( '/[>]/', '/' )
    );

    /**
     * Count all nodes matching the given XPath expression
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @throws \Inphinit\Experimental\Exception
     * @return \DOMNodeList
     */
    public function evaluate($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        if (PHP_VERSION_ID >= 50303) {
            return parent::evaluate($selector, $context, $registerNodeNS);
        } else {
            return parent::evaluate($selector, $context);
        }
    }

    /**
     * Returns a DOMNodeList containing all nodes matching the given XPath expression
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @return \DOMNodeList
     */
    public function query($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        if (PHP_VERSION_ID >= 50303) {
            return parent::query($selector, $context, $registerNodeNS);
        } else {
            return parent::query($selector, $context);
        }
    }

    /**
     * Count all nodes matching the given CSS selector
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @return \DOMNodeList
     */
    public function count($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        $this->boot($selector);

        $selector = $this->toXPath($selector);

        return self::evaluate($selector, $context, $registerNodeNS);
    }

    /**
     * Returns a \DOMNodeList containing all nodes matching the given CSS selector
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @throws \Inphinit\Experimental\Exception
     * @return \DOMNodeList
     */
    public function get($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        $this->boot($selector);

        $selector = $this->toXPath($selector);

        return self::query($selector, $context, $registerNodeNS);
    }

    private function boot(&$query)
    {
        $dot = self::uniqueToken($query, 'dot');
        $hash = self::uniqueToken($query, 'hash');
        $spaces = self::uniqueToken($query, 'space');
        $commas = self::uniqueToken($query, 'comma');
        $child = self::uniqueToken($query, 'child');
        $adjacent = self::uniqueToken($query, 'adjacent');
        $sibling  = self::uniqueToken($query, 'sibling');
        $lbracket = self::uniqueToken($query, 'lbracket');
        $rbracket = self::uniqueToken($query, 'rbracket');
        $lparenthesis = self::uniqueToken($query, 'lparenthesis');
        $rparenthesis = self::uniqueToken($query, 'rparenthesis');

        $this->prevent = array(
            '.' => $dot,
            '#' => $hash,
            ' ' => $spaces,
            ',' => $commas,
            '>' => $child,
            '+' => $adjacent,
            '~' => $sibling,
            '[' => $lbracket,
            ']' => $rbracket,
            '(' => $lparenthesis,
            ')' => $rparenthesis
        );

        $this->restore = array_combine(array_values($this->prevent), array_keys($this->prevent));

        $this->rules = array(
            ' ' => $spaces,
            ',' => $commas
        );

        $query = preg_replace('#\[(\w+)(.)?[=]([^"\'])(.*?)\]#', '[$1$2="$4"]', $query);

        $query = preg_replace_callback('#(["\'])(?:\\.|[^\\\\])*?\1#', array($this, 'preventInQuotes' ), $query);

        $query = preg_replace_callback('#:contains\(((?:\\.|[^\\\\])*?)\)#', array($this, 'preventInContains' ), $query);

        $query = preg_replace('#(\s+)?([>+~\s])(\s+)?#', '\\2', $query);
    }

    private function toXPath($query)
    {
        $queries = explode(',', $query);

        foreach ($queries as &$descendants) {

            $descendants = explode(' ', trim($descendants));

            foreach ($descendants as &$descendant) {
                foreach ($this->qxs as &$qx) {
                    $r = strtr($qx[1], $this->rules);

                    $descendant = trim(preg_replace($qx[0], $r, $descendant));
                }
            }

            $descendants = implode('//', $descendants);
        }

        $query = implode('|', $queries);

        $queries = null;

        return '//' . strtr($query, $this->restore);
    }

    private function preventInQuotes($arg)
    {
        return strtr($arg[0], $this->prevent);
    }

    private function preventInContains($arg)
    {
        if ($arg[1]{0} === '\'' || $arg[1]{0} === '"') {
            return strtr(addcslashes($arg[0], '\\"'), $this->prevent);
        }

        return ':contains("' . strtr($arg[1], $this->prevent) . '")';
    }

    private static function uniqueToken($query, $key, $token = null)
    {
        if ($token === null) {
            $token = time();
        }

        $rt = "`{$key}:{$token}`";

        return strpos($query, $token) === false ? preg_quote($rt) : self::uniqueToken($query, $key, ++$token);
    }
}
