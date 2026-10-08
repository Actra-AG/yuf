<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ErrorHandler;
use actra\yuf\exception\PhpException;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: the registration by `Core` (once per process).
 */
final class ErrorHandlerTest extends TestCase
{
    public function testPhpErrorBecomesAPhpExceptionWithItsOrigin(): void
    {
        $exception = $this->catchPhpException();

        $this->assertSame('Undefined variable $x', $exception->getMessage());
        $this->assertSame(E_WARNING, $exception->getCode());
        $this->assertSame('/app/File.php', $exception->getFile());
        $this->assertSame(12, $exception->getLine());
    }

    private function catchPhpException(): PhpException
    {
        try {
            new ErrorHandler()->handlePhpError(
                errorCode: E_WARNING,
                errorMessage: 'Undefined variable $x',
                errorFile: '/app/File.php',
                errorLine: 12,
            );
        } catch (PhpException $exception) {
            return $exception;
        }
    }

    public function testRegisteredHandlerTurnsWarningsIntoExceptions(): void
    {
        new ErrorHandler()->register();
        try {
            $this->expectException(PhpException::class);
            trigger_error('raised in the test', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }
    }
}
