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
    const FIGURE  = 12;
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
    private $templates = array();

    /**
     * @param bool $enableCustom Enable/disable non-standard inline syntaxes:
     *                           - !highlight! -> <mark>highlight</mark>
     *                           - %variable%  -> <var>variable</var>
     *                           - +inserted+  -> <ins>inserted</ins>
     *                           - -deleted-   -> <del>deleted</del>
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
            $this->setCustomInline('!', '<mark>{contents}</mark>');
            $this->setCustomInline('%', '<var>{contents}</var>');
            $this->setCustomInline('+', '<ins>{contents}</ins>');
            $this->setCustomInline('-', '<del>{contents}</del>');
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
     * Enable/disable use HTML
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
        $this->customInlines[$delimiter] = $template;
    }

    /**
     * Convert markdown string to html string
     *
     * @param string $markdown
     */
    public function fromString($markdown)
    {
        $lines = preg_split('/\r?\n/', $markdown);
        return $this->parseLines($lines, true);
    }

    /**
     * Convert inline string to inline html
     *
     * @param string $markdown
     */
    public function fromInlineString($markdown)
    {
        return $this->resolveInlines($markdown);
    }

    /**
     * Convert markdown file to html string
     *
     * @param string $path
     */
    public function fromFile($path)
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new Exception('Unable to read the file: ' . $path);
        }

        return $this->parseLines($lines, true);
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
            throw new Exception('Invalid template');
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
                    'lang' => htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'),
                ));

                $out .= $code_block . $eol;

                continue;
            }

            // Table
            if ($this->isTableStart($line)) {
                $tableIndex = $i;
                $table = $this->parseTable($lines, $tableIndex);

                if ($table !== '') {
                    $out .= $table;
                    $i = $tableIndex;
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
                        'id' => $id,
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
            $list_match = $this->matchListItem($line);

            if ($list_match !== null && $list_match['indent'] <= 3) {
                if ($topLevel === false) {
                    $out .= $eol;
                }

                $list = $this->parseList($lines, $n, $list_match['ordered'], $i);

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

        return $this->fillTemplate($type, ['contents' => $contents]);
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

        if (strpos($value, '[x] ') === 0) {
            return '<input type="checkbox" checked>' . substr($value, 3);
        }

        if (strpos($value, '[ ] ') === 0) {
            return '<input type="checkbox">' . substr($value, 3);
        }

        if (strpos($value, '[] ') === 0) {
            return '<input type="checkbox">' . substr($value, 2);
        }

        return $value;
    }

    private function parseList(array $lines, $n, $ordered, &$index)
    {
        $start_index = $index;
        $line = isset($lines[$index]) ? $lines[$index] : '';
        $first = $this->matchListItem($line, $ordered);

        if ($first === null) {
            return '';
        }

        $base_indent = $first['indent'];
        $items = array();
        $i = $index;
        $eol = "\n";

        while ($i < $n) {
            $match = $this->matchListItem($lines[$i]);

            if ($match === null || $match['indent'] !== $base_indent || $match['ordered'] !== $ordered) {
                break;
            }

            $itemText = $match['text'];
            ++$i;

            $item_lines = array();

            // Continuation/nested content belongs to this item while it remains
            // indented beyond the list item's indentation. Blank lines are kept
            // when they are followed by another indented line.
            while ($i < $n) {
                $current = $lines[$i];
                $current_indent = $this->indentOf($current);

                if (trim($current) === '') {
                    $j = $i + 1;
                    while ($j < $n && trim($lines[$j]) === '') {
                        ++$j;
                    }

                    if ($j < $n && $this->indentOf($lines[$j]) > $base_indent) {
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

            $inner = $this->resolveInlines($itemText);

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
            'contents' => implode($eol, $items),
        ));
    }

    private function matchListItem($line)
    {
        if (preg_match('/^(\s*)(\d+)[.)]\s+(.*)$/', $line, $matches) === 1) {
            return array(
                'indent' => strlen(str_replace('\t', '    ', $matches[1])),
                'ordered' => true,
                'number' => $matches[2],
                'text' => $matches[3],
            );
        }

        if (preg_match('/^(\s*)([-*+])\s+(.*)$/', $line, $matches) === 1) {
            return array(
                'indent' => strlen(str_replace('\t', '    ', $matches[1])),
                'ordered' => false,
                'marker' => $matches[2],
                'text' => $matches[3],
            );
        }

        return null;
    }

    private function stripListIndent($line, $minimum)
    {
        $indent = $this->indentOf($line);

        if ($indent < $minimum) {
            return ltrim($line, ' \t');
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

        while ($index < $n && trim($lines[$index]) !== '' && $this->isTableDataRow($lines[$index])) {
            $cells = $this->splitTableRow($lines[$index]);

            if (count($cells) < count($headers)) {
                $cells = array_pad($cells, count($headers), '');
            } elseif (count($cells) > count($headers)) {
                $cells = array_slice($cells, 0, count($headers));
            }

            $cellsHtml = array();

            foreach ($cells as $pos => $cell) {
                $attrs = $alignments[$pos] !== '' ? ' align="' . $alignments[$pos] . '"' : '';
                $cellsHtml[] = '<td' . $attrs . '>' . $this->resolveInlines($cell) . '</td>';
            }

            $rows[] = '<tr>' . implode('', $cellsHtml) . '</tr>';
            ++$index;
        }

        $table_headers = array();

        foreach ($headers as $pos => $header) {
            $attrs = $alignments[$pos] !== '' ? ' align="' . $alignments[$pos] . '"' : '';
            $table_headers[] = '<th' . $attrs . '>' . $this->resolveInlines($header) . '</th>';
        }

        $eol = "\n";

        return $this->fillTemplate(self::TABLE, array(
            'headers' => implode('', $table_headers),
            'contents' => implode($eol, $rows),
        )) . $eol;
    }

    private function isTableDataRow($line)
    {
        return strpos($line, '|') !== false;
    }

    private function indentOf($line)
    {
        return strlen($line) - strlen(ltrim($line, ' '));
    }

    private function parseInline($matches)
    {
        if (isset($matches['delimiter'], $matches['contents']) === false) {
            throw new Exception('Invalid regex');
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
                    throw new Exception('Invalid delimiter: ' . $delimiter);
                }

                return $matches['contents'];
        }

        return $this->fillTemplate($type, array('contents' => $matches['contents']));
    }

    private function parseCustomInline($matches)
    {
        if (isset($matches['delimiter'], $matches['contents']) === false) {
            throw new Exception('Invalid regex');
        }

        $contents = $matches['contents'];
        $delimiter = $matches['delimiter'];

        if (isset($this->customInlines[$delimiter]) === false) {
            if ($this->enabledErrors) {
                throw new Exception('Invalid delimiter: ' . $delimiter);
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
                'url' => $src,
            ));
        }

        return $this->fillTemplate(self::FIGURE_CAPTION, array(
            'alternative' => $alt,
            'title' => $matches[3],
            'url' => $src,
        ));
    }

    private function parseAnchor($matches)
    {
        $contents = $matches[1];
        $url = $matches[2];

        if (isset($matches[3]) === false) {
            return $this->fillTemplate(self::ANCHOR, array(
                'contents' => $contents,
                'url' => $url,
            ));
        }

        return $this->fillTemplate(self::ANCHOR_TITLE, array(
            'contents' => $contents,
            'title' => $matches[3],
            'url' => $url,
        ));
    }

    private function resolveInlines($text)
    {
        // Protect escaped characters \X (https://www.markdownguide.org/basic-syntax/#characters-you-can-escape)
        $escapes = array();
        $text = preg_replace_callback(
            '/\\\\([\\\\`*_{}\[\]()#+\-.!|<>])/',
            function ($matches) use (&$escapes) {
                $key = "\x00ESC" . count($escapes) . "\x00";
                $escapes[$key] = $matches[1];
                return $key;
            },
            $text
        );

        // Protect `code`s
        $codes = array();
        $text = preg_replace_callback(
            '/`([^`]+)`/',
            function ($matches) use (&$codes) {
                $key = "\x00CODE" . count($codes) . "\x00";
                $codes[$key] = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                return $key;
            },
            $text
        );

        if ($this->enabledHtml === false) {
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        // Images ![alt](src "title")
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', array($this, 'parseFigure'), $text);

        // Links [texto](href "title")
        $text = preg_replace_callback('/\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', array($this, 'parseAnchor'), $text);

        $inlineCallback = array($this, 'parseInline');

        // Bold (** or __)
        $text = preg_replace_callback('/(?P<delimiter>\*\*)(?P<contents>.+?)\*\*/s', $inlineCallback, $text);
        $text = preg_replace_callback('/(?<!\w)(?P<delimiter>__)(?P<contents>.+?)__(?!\w)/s', $inlineCallback, $text);

        // Italic (* or _)
        $text = preg_replace_callback('/(?P<delimiter>\*)(?P<contents>.+?)\*/s', $inlineCallback, $text);
        $text = preg_replace_callback('/(?<!\w)(?P<delimiter>_)(?P<contents>.+?)_(?!\w)/s', $inlineCallback, $text);

        // Strikethrough (* or _)
        $text = preg_replace_callback('/(?P<delimiter>~~)(?P<contents>.+?)~~/s', $inlineCallback, $text);

        // Subscript (H~2~O -> H<sub>2</sub>O)
        $text = preg_replace_callback('/(?P<delimiter>~)(?P<contents>.+?)~/s', $inlineCallback, $text);

        // Superscript (5^th^ -> 5<sup>th</sup>)
        $text = preg_replace_callback('/(?P<delimiter>\^)(?P<contents>.+?)\^/s', $inlineCallback, $text);

        $customInlineCallback = array($this, 'parseCustomInline');

        foreach ($this->customInlines as $delimiter => $template) {
            $delimiter = preg_quote($delimiter, '/');
            $regex = "/(?P<delimiter>{$delimiter})(?P<contents>.+?){$delimiter}/s";
            $text = preg_replace_callback($regex, $customInlineCallback, $text);
        }

        // Restore `code`s
        foreach ($codes as $key => $value) {
            $template = $this->fillTemplate(self::CODE, array('contents' => $value));
            $text = str_replace($key, $template, $text);
        }

        // Restore escaped characters
        foreach ($escapes as $key => $ch) {
            $text = str_replace($key, htmlspecialchars($ch, ENT_QUOTES, 'UTF-8'), $text);
        }

        return $text;
    }
}
