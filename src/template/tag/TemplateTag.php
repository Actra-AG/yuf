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
 * Extension point: an own tag of a project. Implement this interface (one `final` class per tag, dependencies through
 * the constructor, no static state) and pass the tags to `Core::prepareHttpResponse(templateTags: [...])`; views,
 * snippets, tables and error pages know them then. The engine calls `render()` for every `{tst:name attr='value'}` and
 * `<tst:name attr="value"/>` of the tag's name and puts the returned HTML into the page.
 *
 * `render()` returns HTML and the tag is responsible for escaping: the returned HTML is not escaped again, so escape
 * every value that is not HTML yourself (`TemplateTagContext::escape()` for output, `text()` for values that are not
 * output). An element tag `<tst:name>…</tst:name>` gets its children as `$body` closure; call it to render them. The
 * attributes are given as written in the template (values are strings, selectors are not resolved: use
 * `$context->resolve()`). The name must not be one of a built-in tag, another own tag, `if`, `else` or `for`.
 */
interface TemplateTag
{
    /**
     * The name of the tag in the templates, e.g. `text` for `{tst:text …}`. It must be unique in a
     * `TemplateTagCollection`.
     */
    public function getName(): string;

    /**
     * @param array<string, string> $attributes The attributes of the tag, in the template as written
     * @param (Closure(): string)|null $body Renders the children of an element tag `<tst:name>…</tst:name>`, `null`
     *                                       for inline tags and `<tst:name/>`
     *
     * @return string HTML
     *
     * @throws TemplateException for a missing attribute or a value that cannot be output; the engine adds the file and
     *                           the line
     */
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string;
}
