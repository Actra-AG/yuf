<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use ArrayAccess;
use Override;

/**
 * An `ArrayAccess` object with a key and a public property of the same name, for the selector resolver tests.
 *
 * @implements ArrayAccess<string, string>
 */
final class KeyedObject implements ArrayAccess
{
    public string $name = 'property';

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'name';
    }

    #[Override]
    public function offsetGet(mixed $offset): string
    {
        return 'key';
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void {}

    #[Override]
    public function offsetUnset(mixed $offset): void {}
}
