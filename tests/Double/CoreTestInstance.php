<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double;

use actra\yuf\Core;
use Closure;
use LogicException;
use ReflectionClass;

/**
 * Registers a Core instance without running its constructor (which needs an env file, SSL and a document root),
 * so classes that read Core::get() can be rendered in unit tests.
 */
final class CoreTestInstance
{
    public static function register(string $cacheDirectory, ?string $snippetsDirectory = null): void
    {
        $reflection = new ReflectionClass(objectOrClass: Core::class);
        $core = $reflection->newInstanceWithoutConstructor();
        $initialize = Closure::bind(
            closure: function () use ($cacheDirectory, $snippetsDirectory): void {
                $this->frameworkDirectory = __DIR__ . '/../../src/'; // @phpstan-ignore property.readOnlyAssignOutOfClass
                $this->cacheDirectory = $cacheDirectory; // @phpstan-ignore property.readOnlyAssignOutOfClass
                $this->baseDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR;
                if ($snippetsDirectory !== null) {
                    $this->snippetsDirectory = $snippetsDirectory; // @phpstan-ignore property.readOnlyAssignOutOfClass
                }
            },
            newThis: $core,
            newScope: Core::class,
        );
        if ($initialize === null) {
            throw new LogicException(message: 'Could not bind the initializer to Core.');
        }
        $initialize();
        $reflection->setStaticPropertyValue(name: 'instance', value: $core);
    }
}
