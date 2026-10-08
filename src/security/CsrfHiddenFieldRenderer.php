<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

/**
 * Renders the CSRF token as hidden field for hand-written forms (the `csrfField` placeholder of pages and error
 * pages, the table filter). Forms of yuf render it through their `CsrfTokenField`.
 */
final readonly class CsrfHiddenFieldRenderer
{
    /**
     * @return string The `input` element, or an empty string without a token source (no session, so no CSRF risk)
     */
    public static function render(?CsrfTokenSource $csrfTokenSource): string
    {
        if ($csrfTokenSource === null) {
            return '';
        }

        // The token is a base64 string without characters that need to be HTML encoded
        return '<input type="hidden" name="' . CsrfTokenSource::FIELD_NAME . '" value="' . $csrfTokenSource->getToken() . '">';
    }
}
