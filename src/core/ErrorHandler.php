<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\PhpException;

/**
 * Turns every PHP error (warning, notice, deprecation, …) into a `PhpException`. Registered once by `Core`, which
 * guards against a second registration.
 *
 * @internal
 */
final class ErrorHandler
{
    public function register(): void
    {
        set_error_handler(callback: $this->handlePhpError(...));
    }

    /**
     * @throws PhpException always
     */
    public function handlePhpError(int $errorCode, string $errorMessage, string $errorFile, int $errorLine): never
    {
        throw new PhpException(message: $errorMessage, code: $errorCode, file: $errorFile, line: $errorLine);
    }
}
