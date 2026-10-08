<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

/**
 * Marks a string as HTML that is output as it is. Created for the text of the html classes (`TemplateData`); a value
 * is never trusted because of its content.
 *
 * @internal
 */
final readonly class TrustedHtml
{
    public function __construct(public string $html) {}
}
