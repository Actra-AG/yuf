<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use LogicException;

/**
 * Remembers the form names of the current request, so two forms cannot share a name (the name is the sent indicator
 * and the prefix of the field names in the page).
 *
 * This is the one deliberately kept piece of global state in `src/form/`: the check needs a memory that lives for
 * the whole request and spans independent `Form` instances. It is public only because `Form` lives in another
 * namespace, projects do not use it; tests that build the same form name twice call `reset()`.
 *
 * @internal
 */
final class FormNameRegistry
{
    /** @var list<string> */
    private static array $names = [];

    /**
     * @throws LogicException If a form with this name has already been registered.
     */
    public static function register(string $name): void
    {
        if (in_array(needle: $name, haystack: FormNameRegistry::$names, strict: true)) {
            throw new LogicException(message: 'A Form with the name "' . $name . '" has already been defined.');
        }
        FormNameRegistry::$names[] = $name;
    }

    /**
     * Forgets all form names (for tests).
     */
    public static function reset(): void
    {
        FormNameRegistry::$names = [];
    }
}
