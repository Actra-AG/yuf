<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use Exception;

class PhpException extends Exception
{
    public function __construct(string $message, int $code, string $file, int $line)
    {
        parent::__construct(message: $message, code: $code);
        $this->file = $file;
        $this->line = $line;
    }
}