<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\template\TemplateException;
use Closure;

/**
 * A tag of the template engine, the extension point of projects. The engine calls `render()` for every
 * `{tst:name attr='value'}` and `<tst:name attr="value"/>` of the tag's name and puts the returned HTML into the page.
 * The tag gets what it needs through its constructor and must not use static state.
 *
 * The returned HTML is not escaped again: escape every value that is not HTML yourself (`TemplateTagContext::escape()`).
 */
interface TemplateTag
{
    /**
     * The name of the tag in the templates, e.g. `text` for `{tst:text …}`. It must be unique in a `TemplateTagCollection`.
     */
    public function getName(): string;

    /**
     * @param array<string, string> $attributes The attributes of the tag, in the template as written
     * @param (Closure(): string)|null $body Renders the children of an element tag `<tst:name>…</tst:name>`, `null` for
     *                                       inline tags and `<tst:name/>`
     *
     * @return string HTML
     *
     * @throws TemplateException for a missing attribute or a value that cannot be output; the engine adds the file and the line
     */
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string;
}
