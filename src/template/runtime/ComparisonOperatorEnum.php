<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

/**
 * The operators of the `if` tag.
 *
 * @internal
 */
enum ComparisonOperatorEnum: string
{
    case EQ = 'eq';
    case NE = 'ne';
    case GT = 'gt';
    case GE = 'ge';
    case LT = 'lt';
    case LE = 'le';
    case IN = 'in';

    /**
     * The attribute is case-insensitive.
     */
    public static function tryFromAttribute(string $attribute): ?ComparisonOperatorEnum
    {
        return ComparisonOperatorEnum::tryFrom(value: strtolower(string: $attribute));
    }
}
