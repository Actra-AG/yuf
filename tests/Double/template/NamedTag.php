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
 * A tag with a given name that outputs nothing, to test the registration of tag names.
 */
final readonly class NamedTag implements TemplateTag
{
    public function __construct(private string $name) {}

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        return '';
    }
}
