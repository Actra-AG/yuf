<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use Exception;

/**
 * A PHP error (warning, notice, deprecation, …) that `ErrorHandler` turned into an exception; file and line are those
 * of the error, not of the place where the exception was created.
 */
final class PhpException extends Exception
{
    public function __construct(string $message, int $code, string $file, int $line)
    {
        parent::__construct(message: $message, code: $code);
        $this->file = $file;
        $this->line = $line;
    }
}
