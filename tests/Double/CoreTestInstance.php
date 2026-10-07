<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double;

use actra\yuf\Core;
use Closure;
use ReflectionClass;

/**
 * Registers a Core instance without running its constructor (which needs an env file, SSL and a document root),
 * so classes that read Core::get() can be rendered in unit tests.
 */
final class CoreTestInstance
{
    public static function register(string $cacheDirectory): void
    {
        $reflection = new ReflectionClass(objectOrClass: Core::class);
        $core = $reflection->newInstanceWithoutConstructor();
        Closure::bind(
            closure: function () use ($cacheDirectory): void {
                $this->frameworkDirectory = __DIR__ . '/../../src/'; // @phpstan-ignore property.readOnlyAssignOutOfClass
                $this->cacheDirectory = $cacheDirectory; // @phpstan-ignore property.readOnlyAssignOutOfClass
                $this->baseDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
            },
            newThis: $core,
            newScope: Core::class,
        )();
        $reflection->setStaticPropertyValue(name: 'instance', value: $core);
    }
}
