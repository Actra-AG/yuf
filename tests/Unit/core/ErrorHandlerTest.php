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
        $previousLevel = error_reporting(E_ALL);
        try {
            new ErrorHandler()->handlePhpError(
                errorCode: E_WARNING,
                errorMessage: 'Undefined variable $x',
                errorFile: '/app/File.php',
                errorLine: 12,
            );
        } catch (PhpException $exception) {
            return $exception;
        } finally {
            error_reporting($previousLevel);
        }

        self::fail('No PhpException was thrown.');
    }

    public function testDeprecationThrowsIfReported(): void
    {
        $previousLevel = error_reporting(E_ALL);
        try {
            $this->expectException(PhpException::class);
            new ErrorHandler()->handlePhpError(
                errorCode: E_DEPRECATED,
                errorMessage: 'Deprecated',
                errorFile: '/app/File.php',
                errorLine: 1,
            );
        } finally {
            error_reporting($previousLevel);
        }
    }

    public function testLevelOutsideErrorReportingIsLeftToPhp(): void
    {
        $previousLevel = error_reporting(E_ALL & ~E_WARNING);
        try {
            $handled = new ErrorHandler()->handlePhpError(
                errorCode: E_WARNING,
                errorMessage: 'Not reported',
                errorFile: '/app/File.php',
                errorLine: 1,
            );
        } finally {
            error_reporting($previousLevel);
        }

        $this->assertFalse($handled);
    }

    public function testErrorsAreIgnoredIfErrorReportingIsZero(): void
    {
        $previousLevel = error_reporting(0);
        try {
            $handled = new ErrorHandler()->handlePhpError(
                errorCode: E_USER_ERROR,
                errorMessage: 'Silenced',
                errorFile: '/app/File.php',
                errorLine: 1,
            );
        } finally {
            error_reporting($previousLevel);
        }

        $this->assertFalse($handled);
    }

    public function testRegisteredHandlerIgnoresSilencedErrors(): void
    {
        $previousLevel = error_reporting(E_ALL);
        new ErrorHandler()->register();
        try {
            $result = @file_get_contents(filename: '/nonexistent/yuf-error-handler-test');
        } finally {
            restore_error_handler();
            error_reporting($previousLevel);
        }

        $this->assertFalse($result);
    }

    public function testRegisteredHandlerTurnsWarningsIntoExceptions(): void
    {
        $previousLevel = error_reporting(E_ALL);
        new ErrorHandler()->register();
        try {
            $this->expectException(PhpException::class);
            trigger_error('raised in the test', E_USER_WARNING);
        } finally {
            restore_error_handler();
            error_reporting($previousLevel);
        }
    }
}
