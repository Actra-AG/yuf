<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use InvalidArgumentException;

/**
 * Reduces HTML from an external source (e.g. product texts of an API) to an allowlist of tags and attributes. Parsed
 * like a browser does (HTML5): disallowed elements are unwrapped (their text is kept), `script`, `style` and
 * `template` are removed with their content, comments and all other attributes are dropped.
 */
final class HtmlSanitizer
{
    /**
     * Text formatting, lists and tables; no attributes except `colspan`/`rowspan`, no URLs
     */
    public const array DEFAULT_ALLOWED_TAGS = [
        'p' => [],
        'br' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'sub' => [],
        'sup' => [],
        'table' => [],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];
    private const string HTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';
    private const array REMOVED_TAGS = ['script', 'style', 'template'];
    private const array FORBIDDEN_ATTRIBUTES = ['style', 'srcset'];
    private const array URL_ATTRIBUTES = ['href', 'src', 'cite', 'action', 'formaction', 'poster', 'background'];
    private const array ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * @param array<string, list<string>> $allowedTags Lowercase tag name => allowed lowercase attribute names; URLs
     *     in allowed URL attributes (`href`, `src`, …) are kept only for http, https, mailto, tel or without scheme
     *
     * @throws InvalidArgumentException if the allowlist contains `script`, `style`, `template`, the attributes
     *     `style`, `srcset`, event handlers (`on*`) or names that are not lowercase
     */
    public static function sanitize(string $html, array $allowedTags): string
    {
        HtmlSanitizer::checkAllowedTags(allowedTags: $allowedTags);
        if (trim(string: $html) === '') {
            return '';
        }
        $document = HTMLDocument::createEmpty();
        $source = $document->createElement(localName: 'div');
        $source->innerHTML = $html;
        $target = $document->createElement(localName: 'div');
        HtmlSanitizer::copyChildren(document: $document, from: $source, to: $target, allowedTags: $allowedTags);
        // Unwrapping can leave nesting that the parser builds differently (e.g. li in li); parsing the result again
        // makes the output idempotent
        $source->innerHTML = $target->innerHTML;

        return $source->innerHTML;
    }

    /**
     * @param array<string, list<string>> $allowedTags
     */
    private static function checkAllowedTags(array $allowedTags): void
    {
        foreach ($allowedTags as $tagName => $attributeNames) {
            if (preg_match(pattern: '/^[a-z][a-z0-9]*$/', subject: $tagName) !== 1) {
                throw new InvalidArgumentException(message: 'Invalid tag name: ' . $tagName);
            }
            if (in_array(needle: $tagName, haystack: HtmlSanitizer::REMOVED_TAGS, strict: true)) {
                throw new InvalidArgumentException(message: 'Tag not allowed: ' . $tagName);
            }
            foreach ($attributeNames as $attributeName) {
                if (preg_match(pattern: '/^[a-z][a-z0-9-]*$/', subject: $attributeName) !== 1) {
                    throw new InvalidArgumentException(message: 'Invalid attribute name: ' . $attributeName);
                }
                if (
                    str_starts_with(haystack: $attributeName, needle: 'on')
                    || in_array(needle: $attributeName, haystack: HtmlSanitizer::FORBIDDEN_ATTRIBUTES, strict: true)
                ) {
                    throw new InvalidArgumentException(message: 'Attribute not allowed: ' . $attributeName);
                }
            }
        }
    }

    /**
     * @param array<string, list<string>> $allowedTags
     */
    private static function copyChildren(HTMLDocument $document, Node $from, Element $to, array $allowedTags): void
    {
        foreach ($from->childNodes as $node) {
            if ($node instanceof Text) {
                $to->append($document->createTextNode(data: $node->data));

                continue;
            }
            if (!$node instanceof Element) {
                // Comments and processing instructions
                continue;
            }
            $tagName = $node->localName;
            if (in_array(needle: $tagName, haystack: HtmlSanitizer::REMOVED_TAGS, strict: true)) {
                continue;
            }
            // Foreign elements (SVG, MathML) are unwrapped even if their name is allowed
            if ($node->namespaceURI !== HtmlSanitizer::HTML_NAMESPACE || !array_key_exists(
                key: $tagName,
                array: $allowedTags,
            )) {
                HtmlSanitizer::copyChildren(document: $document, from: $node, to: $to, allowedTags: $allowedTags);

                continue;
            }
            $element = $document->createElement(localName: $tagName);
            foreach ($allowedTags[$tagName] as $attributeName) {
                $value = $node->getAttribute(qualifiedName: $attributeName);
                if (
                    $value === null
                    || (in_array(needle: $attributeName, haystack: HtmlSanitizer::URL_ATTRIBUTES, strict: true)
                        && !HtmlSanitizer::isAllowedUrl(url: $value))
                ) {
                    continue;
                }
                $element->setAttribute(qualifiedName: $attributeName, value: $value);
            }
            HtmlSanitizer::copyChildren(document: $document, from: $node, to: $element, allowedTags: $allowedTags);
            $to->append($element);
        }
    }

    private static function isAllowedUrl(string $url): bool
    {
        // Browsers ignore control characters and whitespace in the scheme ("java\tscript:")
        $normalizedUrl = preg_replace(pattern: '/[\x00-\x20\x7F]+/', replacement: '', subject: $url) ?? '';
        if (preg_match(pattern: '/^([a-z][a-z0-9+.\-]*):/i', subject: $normalizedUrl, matches: $matches) !== 1) {
            // Relative URL; a colon before the first "/", "?" or "#" would be a scheme
            return preg_match(pattern: '/^[^\/?#]*:/', subject: $normalizedUrl) !== 1;
        }

        return in_array(
            needle: strtolower(string: $matches[1]),
            haystack: HtmlSanitizer::ALLOWED_URL_SCHEMES,
            strict: true,
        );
    }
}
