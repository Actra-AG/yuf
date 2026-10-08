<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use Override;
use Stringable;

/**
 * A `Stringable` value for the template data.
 */
final readonly class StringableValue implements Stringable
{
    public function __construct(private string $value) {}

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
