<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

use actra\yuf\template\TemplateData;

/**
 * The data of a render call as a stack of scopes. A `for` loop pushes a scope with its variable and pops it again, so
 * it never overwrites or removes a value of an outer scope.
 *
 * @internal
 *
 * The values are open (`mixed`); `SelectorResolver` narrows them.
 */
final class TemplateScopes
{
    /** @var list<array<string, mixed>> the innermost scope first, the data of the render call last */
    private array $scopes;

    public function __construct(TemplateData $data)
    {
        $this->scopes = [$data->values];
    }

    public function push(string $name, mixed $value): void
    {
        array_unshift($this->scopes, [$name => $value]);
    }

    public function pop(): void
    {
        if (count(value: $this->scopes) > 1) {
            array_shift(array: $this->scopes);
        }
    }

    public function has(string $name): bool
    {
        foreach ($this->scopes as $scope) {
            if (array_key_exists(key: $name, array: $scope)) {
                return true;
            }
        }

        return false;
    }

    public function get(string $name): mixed
    {
        foreach ($this->scopes as $scope) {
            if (array_key_exists(key: $name, array: $scope)) {
                return $scope[$name];
            }
        }

        return null;
    }
}
