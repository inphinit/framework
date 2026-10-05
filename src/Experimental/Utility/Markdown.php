<?php
/*
 * Inphinit
 *
 * Copyright (c) 2026 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Utility;

use Inphinit\Exception;
use Inphinit\Utility\Strings;

class Markdown
{
    // Contents
    const BLOCKQUOTE = 1;
    const CODE_BLOCK = 2;

    // Title
    const H1 = 3;
    const H2 = 4;
    const H3 = 5;
    const H4 = 6;
    const H5 = 7;
    const H6 = 8;

    // Horizontal line
    const HR = 9;

    // External
    const ANCHOR = 10;
    const ANCHOR_TITLE = 11;
    const FIGURE = 12;
    const FIGURE_CAPTION = 13;

    // List
    const OL = 14;
    const UL = 15;

    // Inline
    const BOLD = 16;
    const CODE = 17;
    const ITALIC = 18;
    const STRIKETHROUGH = 19;

    // Extended
    const TABLE = 20;

    // Inline Extended
    const SUBSCRIPT = 21;
    const SUPERSCRIPT = 22;

    private $customInlines = array();
    private $enabledErrors = false;
    private $enabledHtml = false;
    private $ids = array();
    private $reservedIndex = 0;
    private $templates = array();

    /**
     * @param bool $enableCustom Enable/disable non-standard inline syntaxes:
     *                           - !!highlight!! -> <mark>highlight</mark>
     *                           - %%variable%%  -> <var>variable</var>
     *                           - ++inserted++  -> <ins>inserted</ins>
     *                           - --deleted--   -> <del>deleted</del>
     */
    public function __construct($enableCustom = true)
    {
        // Contents
        $this->setTemplate(self::BLOCKQUOTE, '<blockquote>{contents}</blockquote>');
        $this->setTemplate(self::CODE_BLOCK, '<pre data-lang="{lang}"><code>{contents}</code></pre>');

        // Titles
        $this->setTemplate(self::H1, '<h1>{contents}</h1>');
        $this->setTemplate(self::H2, '<h2 id="{id}"><a href="#{id}">{contents}</a></h2>');
        $this->setTemplate(self::H3, '<h3>{contents}</h3>');
        $this->setTemplate(self::H4, '<h4>{contents}</h4>');
        $this->setTemplate(self::H5, '<h5>{contents}</h5>');
        $this->setTemplate(self::H6, '<h6>{contents}</h6>');

        // Horizontal line
        $this->setTemplate(self::HR, '<hr>');

        // External
        $this->setTemplate(self::ANCHOR, '<a href="{url}">{contents}</a>');
        $this->setTemplate(self::ANCHOR_TITLE, '<a href="{url}" title="{title}">{contents}</a>');
        $this->setTemplate(self::FIGURE, '<figure><img src="{url}" alt="{alternative}"></figure>');
        $this->setTemplate(self::FIGURE_CAPTION, '<figure><img src="{url}" alt="{alternative}"><figcaption>{title}</figcaption></figure>');

        // List
        $this->setTemplate(self::OL, '<ol>{contents}</ol>');
        $this->setTemplate(self::UL, '<ul>{contents}</ul>');

        // Inline
        $this->setTemplate(self::BOLD, '<strong>{contents}</strong>');
        $this->setTemplate(self::CODE, '<code>{contents}</code>');
        $this->setTemplate(self::ITALIC, '<em>{contents}</em>');
        $this->setTemplate(self::STRIKETHROUGH, '<s>{contents}</s>');

        // Extended
        $this->setTemplate(self::TABLE, '<table><thead><tr>{headers}</tr></thead><tbody>{contents}</tbody></table>');

        // Inline Extended
        $this->setTemplate(self::SUBSCRIPT, '<sub>{contents}</sub>');
        $this->setTemplate(self::SUPERSCRIPT, '<sup>{contents}</sup>');

        if ($enableCustom) {
            $this->setCustomInline('!!', '<mark>{contents}</mark>');
            $this->setCustomInline('%%', '<var>{contents}</var>');
            $this->setCustomInline('++', '<ins>{contents}</ins>');
            $this->setCustomInline('--', '<del>{contents}</del>');
        }
    }

    /**
     * Enable/disable parse errors
     *
     * @param bool $enable
     */
    public function enableErrors($enable)
    {
        $this->enabledErrors = $enable;
    }

    /**
     * Enable/disable use HTML in markdown body
     *
     * @param bool $enable
     */
    public function enableHtml($enable)
    {
        $this->enabledHtml = $enable;
    }

    /**
     * Set HTML template
     *
     * @param int $type
     * @param string $template
     */
    public function setTemplate($type, $template)
    {
        $this->templates[$type] = $template;
    }

    /**
     * Set custom inline HTML template
     *
     * @param string $delimiter
     * @param string $template
     */
    public function setCustomInline($delimiter, $template)
    {
        if (is_string($delimiter) === false || isset($delimiter[1]) === false) {
            throw new \RuntimeException('Custom Inline delimiters requires 2 or more character');
        }

        $this->customInlines[$delimiter] = $template;
    }

    /**
     * Convert a markdown string into an HTML string with paragraphs
     *
     * @param string $input
     * @throws \Inphinit\Exception
     * @return string
     */
    public function fromString($input)
    {
        $lines = preg_split('/\r?\n/', $input);

        try {
            return $this->parseLines($lines, true);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * Convert a markdown string into an HTML string (inline) without paragraphs
     *
     * @param string $input
     * @throws \Inphinit\Exception
     * @return string
     */
    public function fromInlineString($input)
    {
        try {
            return $this->resolveInlines($input);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * Convert a markdown file into an HTML string with paragraphs
     *
     * @param string $path
     * @throws \Inphinit\Exception
     * @return string
     */
    public function fromFile($path)
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new Exception('Unable to read the file: ' . $path);
        }

        try {
            return $this->parseLines($lines, true);
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    private function getUniqueId($id)
    {
        $id = strtolower($id);

        if (isset($this->ids[$id]) === false) {
            $this->ids[$id] = 1;

            return $id;
        }

        $this->ids[$id] += 1;

        return $id . '-' . $this->ids[$id];
    }

    private function fillTemplate($type, array $entries)
    {
        if (isset($this->templates[$type]) === false) {
            throw new \RuntimeException('Invalid template');
        }

        $translates = array();

        foreach ($entries as $key => $value) {
            $translates['{' . $key . '}'] = $value;
        }

        return strtr($this->templates[$type], $translates);
    }

    private function parseLines(array $lines, $topLevel)
    {
        $n = count($lines);
        $i = 0;

        $out = '';
        $section_open = false;
        $eol = "\n";

        while ($i < $n) {
            $line = $lines[$i];

            // Skips empty line
            if (trim($line) === '') {
                ++$i;
                continue;
            }

            // ```
            if (preg_match('/^```[ \t]*(\S*)[ \t]*$/', $line, $matches) === 1) {
                $lang = $matches[1];
                ++$i;
                $code_lines = array();

                while ($i < $n && preg_match('/^```\s*$/', $lines[$i]) === 0) {
                    $code_lines[] = $lines[$i];
                    ++$i;
                }

                ++$i; // skip enclose

                $code_block = $this->fillTemplate(self::CODE_BLOCK, array(
                    'contents' => htmlspecialchars(implode($eol, $code_lines), ENT_NOQUOTES, 'UTF-8'),
                    'lang' => $lang ? self::safe($lang) : 'none'
                ));

                $out .= $code_block . $eol;

                continue;
            }

            // Table
            if ($this->isTableStart($line)) {
                $table_index = $i;
                $table = $this->parseTable($lines, $table_index);

                if ($table !== '') {
                    $out .= $table;
                    $i = $table_index;
                    continue;
                }
            }

            // Horizontal Rule
            if (preg_match('/^ {0,3}([-*_])( *\1){2,}\s*$/', $line) === 1) {
                $hr = $this->fillTemplate(self::HR, array());

                $out .= $hr . $eol;
                ++$i;
                continue;
            }

            // h1..h6
            if (preg_match('/^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $matches) === 1) {
                $level  = strlen($matches[1]);
                $text   = $matches[2];
                $inline = $this->resolveInlines($text);

                if ($level === 2) {
                    $id = Strings::ascii($text);
                    $id = preg_replace('/[^\w\-]+/', '-', $id);
                    $id = preg_replace('/--+/', '-', $id);
                    $id = $this->getUniqueId(trim($id, '-'));

                    $h2 = $this->fillTemplate(self::H2, array(
                        'contents' => $inline,
                        'id' => $id
                    ));

                    if ($topLevel) {
                        if ($section_open) {
                            $out .= '</section>' . $eol;
                        }

                        $out .= $eol . '<section>' . $eol;
                        $section_open = true;
                    }

                    $out .= $h2 . $eol . $eol;
                } else {
                    $out .= $this->parseHeading($level, $inline) . $eol . $eol;
                }

                ++$i;
                continue;
            }

            // Blockquote (nested)
            if (preg_match('/^ {0,3}>/', $line) === 1) {
                $blockquote_lines = array();

                while ($i < $n && preg_match('/^ {0,3}>/', $lines[$i]) === 1) {
                    // removes only ONE level of '>' (leaves '>>' etc. for the recursion).
                    $blockquote_lines[] = preg_replace('/^ {0,3}> ?/', '', $lines[$i]);
                    ++$i;
                }

                $inner = $this->parseLines($blockquote_lines, false);

                $blockquote = $this->fillTemplate(self::BLOCKQUOTE, array('contents' => $inner));

                $out  .= $blockquote . $eol;

                continue;
            }

            // Lists (nested lists are handled recursively by parseList())
            if ($this->matchListItem($line, $ordered, $indent) !== false && $indent <= 3) {
                if ($topLevel === false) {
                    $out .= $eol;
                }

                $list = $this->parseList($lines, $n, $ordered, $i);

                $out .= $list . $eol;
                continue;
            }

            // Paragraph (joins consecutive lines)
            $paragraphs = array();

            while ($i < $n && trim($lines[$i]) !== '' && $this->isBlockStart($lines[$i]) === false) {
                $paragraphs[] = trim($lines[$i]);
                ++$i;
            }

            if (empty($paragraphs) === false) {
                $out .= '<p>' . $this->resolveInlines(implode(' ', $paragraphs)) . "</p>\n";
            } else {
                ++$i; // prevent infinity loop
            }
        }

        if ($topLevel && $section_open) {
            $out .= '</section>' . $eol;
        }

        return $out;
    }

    private function parseHeading($level, $contents)
    {
        switch ($level)
        {
            case 1:
                $type = self::H1;
                break;

            case 3:
                $type = self::H3;
                break;

            case 4:
                $type = self::H4;
                break;

            case 5:
                $type = self::H5;
                break;

            default:
                $type = self::H6;
        }

        return $this->fillTemplate($type, array('contents' => $contents));
    }

    private function isBlockStart($line)
    {
        // Detects whether a line starts a "special" block (to determine where a paragraph should end)

        return (
            preg_match('/^```/', $line) === 1
            || preg_match('/^ {0,3}>/', $line) === 1
            || preg_match('/^ {0,3}#{1,6}\s+/', $line) === 1
            || preg_match('/^ {0,3}([-*_])( *\1){2,}\s*$/', $line) === 1
            || preg_match('/^ {0,3}\d+[.)]\s+/', $line) === 1
            || preg_match('/^ {0,3}[-*+]\s+/', $line) === 1
            || $this->isTableStart($line)
        );
    }

    private function parseTask($value)
    {
        $value = ltrim($value);

        if (strpos($value, '[x]') === 0) {
            return '<input type="checkbox" checked> ' . substr($value, 3);
        }

        if (strpos($value, '[ ]') === 0) {
            return '<input type="checkbox"> ' . substr($value, 3);
        }

        if (strpos($value, '[]') === 0) {
            return '<input type="checkbox"> ' . substr($value, 2);
        }

        return $value;
    }

    private function parseList(array $lines, $n, $ordered, &$index)
    {
        $start_index = $index;
        $line = isset($lines[$index]) ? $lines[$index] : '';
        $first = $this->matchListItem($line, $ord, $base_indent);

        if ($first === false) {
            return '';
        }

        $items = array();
        $i = $index;
        $eol = "\n";

        while ($i < $n) {
            $contents = $this->matchListItem($lines[$i], $ord, $indent);

            if ($contents === false || $indent !== $base_indent || $ord !== $ordered) {
                break;
            }

            ++$i;

            $item_lines = array();

            // Continuation/nested content belongs to this item while it remains
            // indented beyond the list item's indentation. Blank lines are kept
            // when they are followed by another indented line.
            while ($i < $n) {
                $current = $lines[$i];
                $current_indent = self::indentOf($current);

                if (trim($current) === '') {
                    $j = $i + 1;

                    while ($j < $n && trim($lines[$j]) === '') {
                        ++$j;
                    }

                    if ($j < $n && self::indentOf($lines[$j]) > $base_indent) {
                        $item_lines[] = '';
                        ++$i;
                        continue;
                    }

                    break;
                }

                if ($current_indent <= $base_indent) {
                    break;
                }

                $item_lines[] = $this->stripListIndent($current, $base_indent + 1);
                ++$i;
            }

            $inner = $this->resolveInlines($contents);

            if (empty($item_lines) === false) {
                $nested = $this->parseLines($item_lines, false);

                if (trim($nested) !== '') {
                    $inner .= $eol . $nested;
                }
            }

            $inner = $this->parseTask($inner);

            $items[] = '<li>' . $inner . '</li>';
        }

        // Avoid a zero-progress loop if malformed input reaches this method.
        if ($i === $start_index) {
            ++$i;
        }

        $index = $i;

        return $this->fillTemplate($ordered ? self::OL : self::UL, array(
            'contents' => implode($eol, $items)
        ));
    }

    private function matchListItem($line, &$ordered, &$indent)
    {
        $ordered = null;

        if (preg_match('/^(\s*)(\d+)[.)]\s+(.*)$/', $line, $matches) === 1) {
            $ordered = true;
            // $type = 'number'; // $matches[2]
        } elseif (preg_match('/^(\s*)([-*+])\s+(.*)$/', $line, $matches) === 1) {
            $ordered = false;
            // $type = 'marker'; // $matches[2]
        }

        if ($ordered === null) {
            return false;
        }

        $indent = strlen(str_replace("\t", '    ', $matches[1]));

        return $matches[3];
    }

    private function stripListIndent($line, $minimum)
    {
        $indent = self::indentOf($line);

        if ($indent < $minimum) {
            return ltrim($line, " \t");
        }

        $remove = min($indent, $minimum);

        return preg_replace('/^[ \t]{' . $remove . '}/', '', $line);
    }

    private function isTableStart($line)
    {
        if (strpos($line, '|') === false) {
            return false;
        }

        return preg_match('/^\s*\|?.*\|.*\|?\s*$/', $line) === 1;
    }

    private function isTableSeparator($line)
    {
        $cells = $this->splitTableRow($line);

        if (count($cells) === 0) {
            return false;
        }

        foreach ($cells as $cell) {
            if (preg_match('/^\s*:?-{3,}:?\s*$/', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function splitTableRow($line)
    {
        $line = trim($line);

        if ($line === '') {
            return array();
        }

        if ($line[0] === '|') {
            $line = substr($line, 1);
        }

        if ($line !== '' && substr($line, -1) === '|' && (strlen($line) < 2 || $line[strlen($line) - 2] !== '\\')) {
            $line = substr($line, 0, -1);
        }

        $cells = array();
        $buffer = '';
        $escaped = false;
        $length = strlen($line);

        for ($i = 0; $i < $length; ++$i) {
            $char = $line[$i];

            if ($escaped) {
                $buffer .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $buffer .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '|') {
                $cells[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $cells[] = trim($buffer);

        return $cells;
    }

    private function parseTable(array $lines, &$index)
    {
        $n = count($lines);

        if ($index + 1 >= $n || !$this->isTableStart($lines[$index]) || !$this->isTableSeparator($lines[$index + 1])) {
            return '';
        }

        $headers = $this->splitTableRow($lines[$index]);
        $separator = $this->splitTableRow($lines[$index + 1]);

        if (count($headers) === 0 || count($headers) !== count($separator)) {
            return '';
        }

        $alignments = array();

        foreach ($separator as $cell) {
            $cell = trim($cell);
            $left = $cell !== '' && $cell[0] === ':';
            $right = $cell !== '' && substr($cell, -1) === ':';

            if ($left && $right) {
                $alignments[] = 'center';
            } elseif ($right) {
                $alignments[] = 'right';
            } elseif ($left) {
                $alignments[] = 'left';
            } else {
                $alignments[] = '';
            }
        }

        $index += 2;

        $rows = array();
        $total_headers = count($headers);

        while ($index < $n && self::isTableDataRow($lines[$index])) {
            $cells = $this->splitTableRow($lines[$index]);

            if (count($cells) < $total_headers) {
                $cells = array_pad($cells, $total_headers, '');
            } elseif (count($cells) > $total_headers) {
                $cells = array_slice($cells, 0, $total_headers);
            }

            $cells_html = array();

            foreach ($cells as $pos => $cell) {
                $attrs = $alignments[$pos] !== '' ? ' style="text-align: ' . $alignments[$pos] . '"' : '';
                $cells_html[] = '<td' . $attrs . '>' . $this->resolveInlines($cell) . "</td>\n";
            }

            $rows[] = '<tr>' . implode('', $cells_html) . '</tr>';
            ++$index;
        }

        $table_headers = array();

        foreach ($headers as $pos => $header) {
            $attrs = $alignments[$pos] !== '' ? ' style="text-align: ' . $alignments[$pos] . '"' : '';
            $table_headers[] = '<th' . $attrs . '>' . $this->resolveInlines($header) . '</th>';
        }

        $eol = "\n";

        return $this->fillTemplate(self::TABLE, array(
            'headers' => implode($eol, $table_headers),
            'contents' => implode($eol, $rows)
        )) . $eol;
    }

    private function parseInline($matches)
    {
        if (isset($matches['delimiter'], $matches['contents']) === false) {
            throw new \RuntimeException('Invalid regex');
        }

        $delimiter = $matches['delimiter'];

        switch ($delimiter) {
            case '~~':
                $type = self::STRIKETHROUGH;
                break;

            case '*':
            case '_':
                $type = self::ITALIC;
                break;

            case '**':
            case '__':
                $type = self::BOLD;
                break;

            case '~':
                $type = self::SUBSCRIPT;
                break;

            case '^':
                $type = self::SUPERSCRIPT;
                break;

            default:
                if ($this->enabledErrors) {
                    throw new \RuntimeException('Invalid delimiter: ' . $delimiter);
                }

                return $matches['contents'];
        }

        return $this->fillTemplate($type, array('contents' => $matches['contents']));
    }

    private function parseCustomInline($matches)
    {
        if (isset($matches['delimiter'], $matches['contents']) === false) {
            throw new \RuntimeException('Invalid regex');
        }

        $contents = $matches['contents'];
        $delimiter = $matches['delimiter'];

        if (isset($this->customInlines[$delimiter]) === false) {
            if ($this->enabledErrors) {
                throw new \RuntimeException('Invalid delimiter: ' . $delimiter);
            }

            return $contents;
        }

        return str_replace('{contents}', $contents, $this->customInlines[$delimiter]);
    }

    private function parseFigure($matches)
    {
        $alt = $matches[1];
        $src = $matches[2];

        if (isset($matches[3]) === false) {
            return $this->fillTemplate(self::FIGURE, array(
                'alternative' => $alt,
                'url' => $src
            ));
        }

        return $this->fillTemplate(self::FIGURE_CAPTION, array(
            'alternative' => $alt,
            'title' => $matches[3],
            'url' => $src
        ));
    }

    private function parseAnchor($matches)
    {
        $contents = $matches[1];
        $url = $matches[2];

        if (isset($matches[3]) === false) {
            return $this->fillTemplate(self::ANCHOR, array(
                'contents' => $contents,
                'url' => $url
            ));
        }

        return $this->fillTemplate(self::ANCHOR_TITLE, array(
            'contents' => $contents,
            'title' => $matches[3],
            'url' => $url
        ));
    }

    private function replaceWithReservedCodes($regex, $text, &$reserveds)
    {
        $reserveds = array();

        $index = $this->reservedIndex;

        $output = preg_replace_callback($regex, function ($matches) use (&$reserveds, &$index) {
            ++$index;

            $key = "\x00RESERVED" . $index . "\x00";
            $reserveds[$key] = $matches[1];

            return $key;
        }, $text);

        $this->reservedIndex = $index;

        return $output;
    }

    private function resolveInlines($text)
    {
        $text = str_replace("\x00", '', $text);

        $to_escape = '\\`*_{}[]<>()#+-.!|' . implode('', array_keys($this->customInlines));
        $to_escape = implode('', array_unique(str_split($to_escape, 1), SORT_STRING));
        $to_escape = preg_quote($to_escape, '/');

        // Protect escaped characters \X (https://www.markdownguide.org/basic-syntax/#characters-you-can-escape)
        $text = $this->replaceWithReservedCodes('/\\\\([' . $to_escape . '])/', $text, $escapes);

        // Protect `code`s
        $text = $this->replaceWithReservedCodes('/`([^`]+)`/', $text, $codes);

        if ($this->enabledHtml === false) {
            $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
        }

        // Images ![alt](src "title")
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', array($this, 'parseFigure'), $text);

        // Quick links
        $text = preg_replace('/[<](\S+?@\S+?|\w+?[:].+?)[>]/', '[$1]($1)', $text);

        // Links [texto](href "title")
        $text = preg_replace_callback('/\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', array($this, 'parseAnchor'), $text);

        // Note: (?<!\w)...(?!\w) preserve snake_case strings

        $custom_inline_callback = array($this, 'parseCustomInline');

        foreach ($this->customInlines as $delimiter => $template) {
            if (strpos($delimiter, '_') !== false) {
                $nprefix = '(?<!\w)';
                $nsufix = '(?!\w)';
            } else {
                $nprefix = '';
                $nsufix = '';
            }

            $delimiter = preg_quote($delimiter, '/');
            $regex = "/{$nprefix}(?P<delimiter>{$delimiter})(?P<contents>.+?){$delimiter}{$nsufix}/s";
            $text = preg_replace_callback($regex, $custom_inline_callback, $text);
        }

        $inline_callback = array($this, 'parseInline');

        // Bold (** or __)
        $text = preg_replace_callback('/(?P<delimiter>\*\*)(?P<contents>.+?)\*\*/', $inline_callback, $text);
        $text = preg_replace_callback('/(?<!\w)(?P<delimiter>__)(?P<contents>.+?)__(?!\w)/', $inline_callback, $text);

        // Italic (* or _)
        $text = preg_replace_callback('/(?P<delimiter>\*)(?P<contents>.+?)\*/', $inline_callback, $text);
        $text = preg_replace_callback('/(?<!\w)(?P<delimiter>_)(?P<contents>.+?)_(?!\w)/', $inline_callback, $text);

        // Strikethrough (~~)
        $text = preg_replace_callback('/(?P<delimiter>~~)(?P<contents>.+?)~~/', $inline_callback, $text);

        // Subscript (H~2~O -> H<sub>2</ub>O)
        $text = preg_replace_callback('/(?P<delimiter>~)(?P<contents>.+?)~/', $inline_callback, $text);

        // Superscript (5^th^ -> 5<sup>th</up>)
        $text = preg_replace_callback('/(?P<delimiter>\^)(?P<contents>.+?)\^/', $inline_callback, $text);

        // Restore `code`s
        foreach ($codes as $key => $value) {
            $template = $this->fillTemplate(self::CODE, array(
                'contents' => self::safe($value)
            ));
            $text = str_replace($key, $template, $text);
        }

        // Restore escaped characters
        foreach ($escapes as $key => $ch) {
            $text = str_replace($key, self::safe($ch), $text);
        }

        return $text;
    }

    private static function safe($input)
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    private static function indentOf($line)
    {
        return strlen($line) - strlen(ltrim($line, ' '));
    }

    private static function isTableDataRow($line)
    {
        return trim($line) !== '' && strpos($line, '|') !== false;
    }
}
