<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\PhpException;
use LogicException;

class ErrorHandler
{
    private static ?ErrorHandler $registeredInstance = null;

    public static function register(): void
    {
        if (!is_null(value: ErrorHandler::$registeredInstance)) {
            throw new LogicException(message: 'ErrorHandler is already registered.');
        }
        ErrorHandler::$registeredInstance = new ErrorHandler();
        set_error_handler(callback: [ErrorHandler::$registeredInstance, 'handlePHPError']);
    }

    public function handlePHPError(int $errorCode, string $errorMessage, string $errorFile, int $errorLine): bool
    {
        throw new PhpException(message: $errorMessage, code: $errorCode, file: $errorFile, line: $errorLine);
    }
}