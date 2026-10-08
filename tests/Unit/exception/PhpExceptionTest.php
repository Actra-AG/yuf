<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\exception\PhpException;
use PHPUnit\Framework\TestCase;

final class PhpExceptionTest extends TestCase
{
    public function testKeepsMessageCodeAndTheOriginOfTheError(): void
    {
        $exception = new PhpException(message: 'Undefined variable', code: E_WARNING, file: '/app/File.php', line: 12);

        $this->assertSame('Undefined variable', $exception->getMessage());
        $this->assertSame(E_WARNING, $exception->getCode());
        $this->assertSame('/app/File.php', $exception->getFile());
        $this->assertSame(12, $exception->getLine());
    }
}
