<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\tag\TemplateTagContext;
use Closure;
use Override;

/**
 * A hand-written own tag: `{tst:shout value='selector'}` outputs the escaped value in upper case, and
 * `<tst:shout>text</tst:shout>` outputs its rendered children in upper case. Used to test the extension point.
 */
final readonly class ShoutTag implements TemplateTag
{
    #[Override]
    public function getName(): string
    {
        return 'shout';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        if ($body !== null) {
            return strtoupper(string: $body());
        }
        $selector = $context->requireAttribute(attributes: $attributes, name: 'value');

        return strtoupper(string: $context->escape(value: $context->resolve(selector: $selector)));
    }
}
