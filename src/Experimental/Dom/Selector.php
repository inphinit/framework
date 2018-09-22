<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Dom;

class Selector extends \DOMXPath
{
    private $prevent;
    private $rules;
    private $qxs = array(
        array( '/^([^a-z*])/', '*\\1' ),
        array( '/([>\+~])([^a-z*])/', '\\1*\\2' ),
        array( '/\[(.*?)\]/', '[@\\1]' ),
        array( '/#(\w+)/', '[@id="\\1"]' ),
        array( '/\:empty/', '[count(*)=0]'),
        array( '/\:last-child/', '[last()]' ),
        array( '/^(.*?)\:first-child $/', 'descendant::\\1' ),
        array( '/\:nth-(last-)?(child|of-type)\(n\)/', '' ),
        array( '/\:nth-child\(odd\)/', ':nth-child(2n+1)' ),
        array( '/\:nth-child\(even\)/', ':nth-child(2n)' ),
        array( '/\:nth-child\((\d+)\)/', '[position() mod \\1 = 1]' ),
        array( '/\:nth-child\((\d+)n\)/', '[position() mod \\1 = 0]' ),
        array( '/\:nth-child\((\d+)n\+(\d+)\)/', '[position() mod \\1 = \\2]' ),
        array( '/\:nth-last-child\(odd\)/', ':nth-last-child(2n+1)' ),
        array( '/\:nth-last-child\(even\)/', ':nth-last-child(2n)' ),
        array( '/\:nth-last-child\((\d+)\)/', '[(count() - position()) mod \\1 = 1]' ),
        array( '/\:nth-last-child\((\d+)n\)/', '[(count() - position()) mod \\1 = 0]' ),
        array( '/\:nth-last-child\((\d+)n\+(\d+)\)/', '[(count() - position()) mod \\1 = \\2]' ),
        array( '/\.(\w+)/', '[@class~="\\1"]' ),
        array( '/\:lang\(([\w\-]+)\)/', '[@lang|="\\1"]' ),
        array( '/\[(@\w+)\^=(["\'])(.*?)\\2\]/', '[starts-with(\\1,\\2\\3\\2)]' ),
        array( '/\[(@\w+)\*=(["\'])(.*?)\\2\]/', '[contains(\\1,\\2\\3\\2)]' ),
        array( '/\[(@\w+)\~=(["\'])(.*?)\\2\]/', '[contains(concat(" ",\\1," "),\\2 \\3 \\2)]' ),
        array( '/\[(@\w+)\|=(["\'])(.*?)\\2\]/', '[starts-with(concat(\\1,"-"),concat(\\2\\3\\2,"-"))]' ),
        array( '/\[(@\w+)\$=(["\'])(.*?)\\2\]/', '[substring(\\1,string-length(\\1)-2)=\\2\\3\\2]' ),
        array( '/\:contains\((.*?)\)/', '[contains(text(),\\1)]' ),
        array( '/[~]([*\w]+)/', '/following-sibling::*[count(\\1)]' ),
        array( '/\+/', '/following-sibling::' ),
        array( '/[>]/', '/' )
    );

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
        return $this->exec('evaluate', $selector, $context, $registerNodeNS);
    }

    /**
     * Returns a \DOMNodeList containing all nodes matching the given CSS selector
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @return \DOMNodeList
     */
    public function get($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        return $this->exec('query', $selector, $context, $registerNodeNS);
    }

    private function exec($method, $query, $context, $registerNodeNS)
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

        $this->rules = array(
            ' ' => $spaces,
            ',' => $commas
        );

        $query = $this->toXPath($query);

        if (PHP_VERSION_ID >= 50303) {
            return $this->$method($query, $context, $registerNodeNS);
        } else if ($context !== null) {
            return $this->$method($query, $context);
        }

        return $this->$method($query);
    }

    private function toXPath($query)
    {
        $query = preg_replace_callback('#\[(\w+)(.)?[=]([^"\'])(.*?)\]#', array($this, 'putQuotes'), $query);

        $query = preg_replace_callback('#\:contains\(([^"\'])(.*?)\)#', array($this, 'putQuotes'), $query);

        $query = preg_replace_callback('#(["\'])(?:\\\\.|[^\\\\])*?\\1#', array($this, 'preventInQuotes'), $query);

        $query = preg_replace('#(\s+)?([>+~\s])(\s+)?#', '\\2', $query);

        $queries = explode(',', $query);

        $restore = array_combine(array_values($this->prevent), array_keys($this->prevent));

        foreach ($queries as &$descendants) {

            $descendants = explode(' ', trim($descendants));

            foreach ($descendants as &$descendant) {
                foreach ($this->qxs as &$qx) {
                    $r = strtr($qx[1], $this->rules);

                    $descendant = trim(preg_replace($qx[0], $r, $descendant));

                    var_dump( strtr($descendant, $restore) . ' => ' . $qx[0] );
                }
            }

            $descendants = implode('//', $descendants);
        }

        $query = implode('|', $queries);

        $queries = null;

        $restore = array_combine(array_values($this->prevent), array_keys($this->prevent));

        return '//' . strtr($query, $restore);
    }

    private function putQuotes($arg)
    {
        if (stripos($arg[0], ':contains') === 0) {
            return ':contains("' . addcslashes($arg[1] . $arg[2], '"') . '")';
        }

        return sprintf('[%s%s="%s"]', $arg[1], $arg[2], addcslashes($arg[3] . $arg[4], '"'));
    }

    private function preventInQuotes($arg)
    {
        $quote = $arg[1];
        $inValue = substr($arg[0], 1, -1);

        if (strpos($inValue, $quote) === false) {
            return strtr($arg[0], $this->prevent);
        }

        $quotec = $quote === '"' ? '\'' : '"';
        $unes = '\\' . $quote;
        $glue = $quote . ',' . $quotec . $quote . $quotec . ',' . $quote;

        $inValue = str_replace($unes, $quote, $inValue);

        $inValue = 'concat(' . $quote . implode($glue, explode($quote, $inValue)) . $quote . ')';

        return strtr($inValue, $this->prevent);
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
