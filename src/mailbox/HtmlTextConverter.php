<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

/**
 * A readable text version of an HTML mail: the text without tags, styles, scripts and comments, entities decoded.
 * Paragraphs, headings, lists and tables are separated by an empty line, other blocks (rows, list items, `div`) and
 * `<br>` start a new line, table cells are separated by a space.
 *
 * @internal
 */
final class HtmlTextConverter
{
    private const array SKIPPED_ELEMENTS = ['head', 'script', 'style', 'template', 'noscript', 'title'];

    private const array PARAGRAPH_ELEMENTS = [
        'blockquote', 'dl', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'ol', 'p', 'pre', 'table', 'ul',
    ];

    private const array BLOCK_ELEMENTS = [
        'address', 'article', 'aside', 'body', 'dd', 'details', 'div', 'dt', 'fieldset', 'figcaption', 'figure',
        'footer', 'form', 'header', 'li', 'main', 'nav', 'section', 'summary', 'tbody', 'tfoot', 'thead', 'tr',
    ];

    /** Marks a paragraph break until the lines are normalized (removed from the text of the document) */
    private const string PARAGRAPH_MARK = "\x01";

    /**
     * @param string $html UTF-8, whatever charset the document declares
     */
    public static function convert(string $html): string
    {
        if (trim(string: $html) === '') {
            return '';
        }
        $document = HTMLDocument::createFromString(
            source: $html,
            options: LIBXML_NOERROR,
            overrideEncoding: 'UTF-8',
        );
        $text = HtmlTextConverter::textOf(node: $document, isPreformatted: false);

        return HtmlTextConverter::normalizeLines(text: $text);
    }

    private static function textOf(Node $node, bool $isPreformatted): string
    {
        if ($node instanceof Text) {
            $data = str_replace(search: HtmlTextConverter::PARAGRAPH_MARK, replace: '', subject: $node->data);

            return $isPreformatted ? $data : (preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $data) ?? '');
        }
        $name = $node instanceof Element ? strtolower(string: $node->localName) : '';
        if (in_array(needle: $name, haystack: HtmlTextConverter::SKIPPED_ELEMENTS, strict: true)) {
            return '';
        }
        if ($name === 'br') {
            return "\n";
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= HtmlTextConverter::textOf(node: $child, isPreformatted: $isPreformatted || $name === 'pre');
        }

        return match (true) {
            in_array(needle: $name, haystack: HtmlTextConverter::PARAGRAPH_ELEMENTS, strict: true)
                => HtmlTextConverter::PARAGRAPH_MARK . $text . HtmlTextConverter::PARAGRAPH_MARK,
            in_array(needle: $name, haystack: HtmlTextConverter::BLOCK_ELEMENTS, strict: true) => "\n" . $text . "\n",
            $name === 'td', $name === 'th' => ' ' . $text . ' ',
            default => $text,
        };
    }

    private static function normalizeLines(string $text): string
    {
        $mark = HtmlTextConverter::PARAGRAPH_MARK;
        $text = preg_replace(pattern: '/[ \t\x{A0}]+/u', replacement: ' ', subject: $text) ?? $text;
        $text = preg_replace(pattern: '/ *\n[ \n]*/', replacement: "\n", subject: $text) ?? $text;
        $text = preg_replace(pattern: '/[ \n]*' . $mark . '[ \n' . $mark . ']*/', replacement: "\n\n", subject: $text);

        return trim(string: $text ?? '');
    }
}
