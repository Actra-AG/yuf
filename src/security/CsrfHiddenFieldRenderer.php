<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

/**
 * Renders the CSRF token as hidden field for hand-written forms (the `csrfField` placeholder of pages and error
 * pages, the table filter). Forms of yuf render it through their `CsrfTokenField`. A pure function of its argument, so
 * it stays static.
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

        $token = htmlspecialchars(
            string: $csrfTokenSource->getToken(),
            flags: ENT_QUOTES | ENT_SUBSTITUTE,
            encoding: 'UTF-8',
        );

        return '<input type="hidden" name="' . CsrfTokenSource::FIELD_NAME . '" value="' . $token . '">';
    }
}
