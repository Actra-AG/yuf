<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use Closure;
use Override;

/**
 * `{tst:text value='selector'}`: outputs the value escaped.
 */
final readonly class TextTag implements TemplateTag
{
    #[Override]
    public function getName(): string
    {
        return 'text';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $selector = $context->requireAttribute(attributes: $attributes, name: 'value');

        return $context->escape(value: $context->resolve(selector: $selector));
    }
}
