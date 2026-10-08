<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\template\runtime\TrustedHtml;
use Closure;
use DateTimeInterface;
use Override;
use Stringable;

/**
 * `{tst:print var='selector'}`: debug output of a value, escaped. Dates are formatted as `Y-m-d H:i:s`, arrays and
 * other objects as `print_r()`.
 */
final readonly class PrintTag implements TemplateTag
{
    #[Override]
    public function getName(): string
    {
        return 'print';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $value = $context->resolve(selector: $context->requireAttribute(attributes: $attributes, name: 'var'));
        if ($value instanceof DateTimeInterface) {
            return $context->escape(value: $value->format(format: 'Y-m-d H:i:s'));
        }
        if ($value === null || is_scalar(value: $value) || $value instanceof Stringable || $value instanceof TrustedHtml) {
            return $context->escape(value: $value);
        }

        return $context->escape(value: print_r(value: $value, return: true));
    }
}
