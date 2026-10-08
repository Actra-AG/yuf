<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\PhpException;

/**
 * Turns every PHP error (warning, notice, deprecation, …) that `error_reporting()` includes into a `PhpException`.
 * Errors outside `error_reporting()`, also those silenced with `@`, are left to PHP's standard handling. Registered
 * once by `Core`, which guards against a second registration.
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
     * @return bool `false` if `error_reporting()` does not include the level, so that PHP continues with its standard
     *              handling; never `true`
     *
     * @throws PhpException if `error_reporting()` includes the level
     */
    public function handlePhpError(int $errorCode, string $errorMessage, string $errorFile, int $errorLine): bool
    {
        if ((error_reporting() & $errorCode) === 0) {
            return false;
        }
        throw new PhpException(message: $errorMessage, code: $errorCode, file: $errorFile, line: $errorLine);
    }
}
