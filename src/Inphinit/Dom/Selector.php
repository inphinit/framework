<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Dom;

class Selector
{
    private $base;
    private $prevent;
    private $rules;
    private $allSiblingsToken;
    private static $cache = array();
    private $qxs = array(
        array('/^([^a-z*])/i', '*\\1'),
        array('/([>+~])([^\w*\s])/', '\\1*\\2'),
        array('/\[(.*?)\]/', '[@\\1\\2]'),
        array('/\.(\w+)/', '[@class~="\\1" i]'),
        array('/#(\w+)/', '[@id="\\1" i]'),
        array('/\:lang\(([\w\-]+)\)/i', '[@lang|="\\1" i]'),
        array('/([>+~]|^)\:nth-(last-)?(child|of-type)\(n\)/i', '\\1*'),
        array('/\:nth-(last-)?(child|of-type)\(n\)/i', ''),
        array('/\:first-child/', ':nth-child(1)'),
        array('/\:nth-child\(odd\)/i', ':nth-child(2n+1)'),
        array('/\:nth-child\(even\)/i', ':nth-child(2n)'),
        array('/([>+~])?\*([^>+~]+)?\:nth-child\((\d+)\)/i', '\\1*[position()=\\3]\\2'),
        array('/([>+~])?\*([^>+~]+)?\:nth-child\((\d+)n\)/i', '\\1*[(position() mod \\3=0)]\\2'),
        array('/([>+~])?\*([^>+~]+)?\:nth-child\((\d+)n\+(\d+)\)/i', '\\1*[(position() mod \\3=\\4)]\\2'),
        array('/([>+~])?(\w+)([^>+~]+)?\:nth-child\((\d+)\)/i', '\\1*[name()="\\2" and position()=\\4]\\3'),
        array('/([>+~])?(\w+)([^>+~]+)?\:nth-child\((\d+)n\)/i', '\\1*[name()="\\2" and (position() mod \\4=0)]\\3'),
        array('/([>+~])?(\w+)([^>+~]+)?\:nth-child\((\d+)n\+(\d+)\)/i', '\\1*[name()="\\2" and (position() mod \\4=\\5)]\\3'),
        array('/([>+~])?\*([^>+~]+)?\:only-child/i', '\\1*[last()=1]\\2'),
        array('/([>+~])?\*([^>+~]+)?\:last-child/i', '\\1*[position()=last()]\\2'),
        array('/([>+~])?(\w+)([^>+~]+)?\:only-child/i', '\\1*[name()="\\2" and last()=1]\\3'),
        array('/([>+~])?(\w+)([^>+~]+)?\:last-child/i', '\\1*[name()="\\2" and position()=last()]\\3'),
        array('/\:empty/i', '[not(text())]'),
        array('/\[(@\w+)(.)?=(.*?) i]/', '[lower-case(\\1)\\2=lower-case(\\3)]'),
        array('/\[(@\w+|lower-case\(@\w+\))\*=(.*?)\]/', '[contains(\\1,\\2)]'),
        array('/\[(@\w+|lower-case\(@\w+\))\^=(.*?)\]/', '[starts-with(\\1,\\2)]'),
        array('/\[(@\w+|lower-case\(@\w+\))\~=(.*?)\]/', '[contains(concat(" ",\\1," "),concat(" ",\\2," "))]'),
        array('/\[(@\w+|lower-case\(@\w+\))\|=(.*?)\]/', '[starts-with(concat(\\1,"-"),concat(\\2,"-"))]'),
        array('/\[(@\w+|lower-case\(@\w+\))\$=(.*?)\]/', '[substring(\\1,string-length(\\1)-2)=\\2]'),
        array('/\:contains\((.*?)\)/i', '[contains(.,\\1)]'),
        array('/\:contains-child\((.*?)\)/i', '[text()[contains(.,\\1)]]')
    );

    /**
     * Create a `Inphinit\Dom\Selector` instance.
     *
     * @param \DOMDocument $document
     * @param bool $registerNodeNS
     */
    public function __construct(\DOMDocument $document, $registerNodeNS = true)
    {
        if (PHP_VERSION_ID >= 800000) {
            $this->base = new \DOMXPath($document, $registerNodeNS);
        } else {
            $this->base = new \DOMXPath($document);
        }
    }

    /**
     * Get DOMXPath instance
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @return \DOMXPath
     */
    public function xpath()
    {
        return $this->base;
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
        return $this->exec('evaluate', $selector, $context, $registerNodeNS);
    }

    /**
     * Returns a \DOMNodeList that match the specified selector
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @return \DOMNodeList
     */
    public function all($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        return $this->exec('query', $selector, $context, $registerNodeNS);
    }

    /**
     * Returns the first element or node within the document that matches the specified selector.
     * If no matches are found, null is returned.
     *
     * @param string $selector
     * @param \DOMNode $context
     * @param bool $registerNodeNS
     * @return \DOMElement|\DOMNode|\DOMNameSpaceNode|null
     */
    public function first($selector, \DOMNode $context = null, $registerNodeNS = true)
    {
        $nodes = $this->exec('query', $selector, $context, $registerNodeNS);
        return $nodes ? $nodes->item(0) : null;
    }

    private function exec($method, $query, $context, $registerNodeNS)
    {
        return $this->base->$method($this->toXPath($query), $context, $registerNodeNS);
    }

    private function tokens($query)
    {
        $dot = self::uniqueToken($query, 'dot');
        $hash = self::uniqueToken($query, 'hash');
        $spaces = self::uniqueToken($query, 'space');
        $commas = self::uniqueToken($query, 'comma');
        $child = self::uniqueToken($query, 'child');
        $sibling = self::uniqueToken($query, 'sibling');
        $adjacent = self::uniqueToken($query, 'adjacent');
        $lbracket = self::uniqueToken($query, 'lbracket');
        $rbracket = self::uniqueToken($query, 'rbracket');
        $lparenthesis = self::uniqueToken($query, 'lparenthesis');
        $rparenthesis = self::uniqueToken($query, 'rparenthesis');
        $lowercasefunction = self::uniqueToken($query, 'lowercase');

        $this->prevent = array(
            '.' => $dot,
            '#' => $hash,
            ' ' => $spaces,
            ',' => $commas,
            '>' => $child,
            '~' => $sibling,
            '+' => $adjacent,
            '[' => $lbracket,
            ']' => $rbracket,
            '(' => $lparenthesis,
            ')' => $rparenthesis,
            'lower-case' => $lowercasefunction
        );

        $this->rules = array(
            ' ' => $spaces,
            ',' => $commas
        );

        $this->allSiblingsToken = self::uniqueToken($query, 'allSiblings');
    }

    private function toXPath($query)
    {
        if (isset(self::$cache[$query])) {
            return self::$cache[$query];
        }

        $this->tokens($query);

        $query = preg_replace_callback('#\[(\w+)(.)?[=]([^"\'])(.*?)\]#', array($this, 'putQuotes'), $query);

        $preventToken = $this->prevent[' '];

        $caseinsensitive = '[\\1\\2=\\3' . $preventToken . 'i]';

        $query = preg_replace('#\[(\w+)(.)?[=](.*?) i\]#', $caseinsensitive, $query);

        $query = preg_replace_callback('#\:contains\(([^"\'])(.*?)\)#', array($this, 'putQuotes'), $query);

        $query = preg_replace_callback('#(["\'])(?:\\\\.|[^\\\\])*?\\1#', array($this, 'preventInQuotes'), $query);

        $query = preg_replace('#(\s+)?([>+~\s])(\s+)?#', '\\2', $query);

        $queries = explode(',', $query);

        foreach ($queries as &$descendants) {
            $descendants = explode(' ', trim($descendants));

            foreach ($descendants as &$descendant) {
                foreach ($this->qxs as &$qx) {
                    $r = strtr($qx[1], $this->rules);

                    $descendant = str_replace($preventToken . 'i]', ' i]', $descendant);
                    $descendant = trim(preg_replace($qx[0], $r, $descendant));
                }

                $childs = explode('>', $descendant);

                foreach ($childs as &$child) {
                    $child = $this->siblingConvert($child);
                }

                $descendant = implode('/', $childs);
            }

            $descendants = implode('//', $descendants);
        }

        $query = implode('|', $queries);

        $query = preg_replace('#\[(\d+)\]#', $this->prevent['['] . '\\1' . $this->prevent[']'], $query);

        $query = str_replace('][', ' and ', $query);

        $query = preg_replace('#( and |\[)(preceding-sibling::(\*|\w+))\[1 and #', '\\1\\2[1][', $query);

        $query = preg_replace('#lower-case\((.*?)\)#', 'translate(\\1,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")', $query);

        $queries = null;

        $restore = array_combine(array_values($this->prevent), array_keys($this->prevent));

        return self::$cache[$query] = '//' . strtr($query, $restore);
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

    private function siblingConvert($query)
    {
        $query = preg_replace('/([+~])([^+~]+)/', '\\1' . $this->allSiblingsToken . '\\2', $query);

        $siblings = explode($this->allSiblingsToken, $query);

        $last = array_pop($siblings);

        $preceding = '';

        foreach ($siblings as $sibling) {
            $i = substr($sibling, -1) === '+' ? '[1]' : '';

            $sibling = substr($sibling, 0, -1);

            $sibling = preg_replace('/^\*(.*)$/', '[(preceding-sibling::*' . $i . '\\1' . $preceding . ')]', $sibling);

            $sibling = preg_replace('/^(\w+)(.*)$/', '[(preceding-sibling::*' . $i . '[name()="\\1"])\\2' . $preceding . ']', $sibling);

            $preceding = $sibling;
        }

        $siblings = null;

        return $last . $preceding;
    }

    private static function uniqueToken($query, $key, $token = null)
    {
        if ($token === null) {
            $token = time();
        }

        $rt = "`{$key}:{$token}`";

        return strpos($query, (string) $token) === false ? preg_quote($rt) : self::uniqueToken($query, $key, ++$token);
    }
}
