<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use AllowDynamicProperties;

/**
 * Template data object with dynamic properties, which belong to one object and not to its class.
 */
#[AllowDynamicProperties]
final class DynamicSelectorTarget
{
    public function getTitle(): string
    {
        return 'getter title';
    }
}
