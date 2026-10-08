<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use actra\yuf\clock\Clock;
use Closure;
use Override;

/**
 * `{tst:date format='Y'}`: the current date and time of the `Clock` in the given `DateTimeImmutable::format()` format.
 */
final readonly class DateTag implements TemplateTag
{
    public function __construct(private Clock $clock) {}

    #[Override]
    public function getName(): string
    {
        return 'date';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $format = $context->requireAttribute(attributes: $attributes, name: 'format');

        return $context->escape(value: $this->clock->now()->format(format: $format));
    }
}
