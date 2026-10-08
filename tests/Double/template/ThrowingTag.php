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
use RuntimeException;

/**
 * A tag that renders its children, which opens an output buffer, and then fails with an exception that is not a
 * `TemplateException`.
 */
final readonly class ThrowingTag implements TemplateTag
{
    #[Override]
    public function getName(): string
    {
        return 'throwing';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        echo 'partial output';
        ob_start();
        echo 'in a nested buffer';

        throw new RuntimeException(message: 'The tag failed');
    }
}
