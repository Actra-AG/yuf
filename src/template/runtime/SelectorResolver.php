<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateException;
use ArrayAccess;
use LogicException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Resolves selectors like `a.b.c` (design section 2). The first part is a key of the template data; every further part
 * is, in this order: an array or `ArrayAccess` key, a public property, a public getter (`getB()`, `isB()`, `hasB()`) or
 * a public method `b()` without arguments.
 *
 * The values of arrays, properties and methods are untyped, so `narrow()` is the one place where they get a type.
 *
 * @internal
 *
 * @phpstan-import-type TemplateValue from TemplateData
 */
final readonly class SelectorResolver
{
    private const array GETTER_PREFIXES = ['get', 'is', 'has'];

    /**
     * @return TemplateValue
     *
     * @throws TemplateException
     */
    public function resolve(string $selector, TemplateScopes $scopes): bool|int|float|string|object|array|null
    {
        $parts = explode(separator: '.', string: $selector);
        $name = array_shift(array: $parts);
        if (!$scopes->has(name: $name)) {
            throw new TemplateException(
                reason: 'The template data "' . $name . '" does not exist. Check that the view provides a replacement with this identifier',
            );
        }
        $value = $this->narrow(value: $scopes->get(name: $name));
        $path = $name;
        foreach ($parts as $part) {
            $value = $this->resolvePart(value: $value, part: $part, path: $path);
            $path .= '.' . $part;
        }

        return $value;
    }

    /**
     * @return TemplateValue
     *
     * @throws LogicException if the value is not a template value (a resource)
     */
    public function narrow(mixed $value): bool|int|float|string|object|array|null
    {
        if ($value === null || is_scalar(value: $value) || is_array(value: $value) || is_object(value: $value)) {
            return $value;
        }

        throw new LogicException(message: 'Unsupported template value of type ' . get_debug_type(value: $value));
    }

    /**
     * @param TemplateValue $value
     *
     * @return TemplateValue
     */
    private function resolvePart(bool|int|float|string|object|array|null $value, string $part, string $path): bool|int|float|string|object|array|null
    {
        if (is_array(value: $value)) {
            return $this->resolveArrayKey(array: $value, key: $part, path: $path);
        }
        if (!is_object(value: $value) || $value instanceof TrustedHtml) {
            throw new TemplateException(
                reason: 'Cannot read "' . $part . '" of "' . $path . '": the value is not an array and not an object',
            );
        }
        // ArrayAccess implementations name the offset parameter differently (ArrayObject: $key), so no named arguments
        if ($value instanceof ArrayAccess && $value->offsetExists($part)) {
            return $this->narrow(value: $value->offsetGet($part));
        }

        return $this->resolveObjectMember(object: $value, name: $part, path: $path);
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return TemplateValue
     */
    private function resolveArrayKey(array $array, string $key, string $path): bool|int|float|string|object|array|null
    {
        if (!array_key_exists(key: $key, array: $array)) {
            throw new TemplateException(reason: 'The array "' . $path . '" has no key "' . $key . '"');
        }

        return $this->narrow(value: $array[$key]);
    }

    /**
     * @return TemplateValue
     */
    private function resolveObjectMember(object $object, string $name, string $path): bool|int|float|string|object|array|null
    {
        if (property_exists(object_or_class: $object, property: $name)) {
            $property = new ReflectionProperty(class: $object, property: $name);
            if ($property->isPublic()) {
                return $this->narrow(value: $property->getValue(object: $object));
            }
        }
        $method = $this->findMethod(object: $object, name: $name);
        if ($method === null) {
            throw new TemplateException(
                reason: 'Cannot read "' . $name . '" of "' . $path . '": no key, public property, getter or method without arguments of this name',
            );
        }

        return $this->narrow(value: $method->invoke(object: $object));
    }

    private function findMethod(object $object, string $name): ?ReflectionMethod
    {
        $candidates = [];
        foreach (SelectorResolver::GETTER_PREFIXES as $prefix) {
            $candidates[] = $prefix . ucfirst(string: $name);
        }
        $candidates[] = $name;
        foreach ($candidates as $candidate) {
            if (!method_exists(object_or_class: $object, method: $candidate)) {
                continue;
            }
            $method = new ReflectionMethod(objectOrMethod: $object, method: $candidate);
            if ($this->isPublicWithoutArguments(method: $method)) {
                return $method;
            }
        }

        return null;
    }

    private function isPublicWithoutArguments(ReflectionMethod $method): bool
    {
        return $method->isPublic() && !$method->isStatic() && $method->getNumberOfRequiredParameters() === 0;
    }
}
