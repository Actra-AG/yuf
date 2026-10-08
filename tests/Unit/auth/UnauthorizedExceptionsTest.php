<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

final class UnauthorizedExceptionsTest extends TestCase
{
    public function testAccessRightExceptionIsAnUnauthorizedExceptionWithStatus401(): void
    {
        $this->expectException(UnauthorizedException::class);

        throw new UnauthorizedAccessRightException();
    }

    public function testAccessRightExceptionKeepsCodeAndMessage(): void
    {
        $exception = new UnauthorizedAccessRightException();

        $this->assertSame(HttpStatusCodeEnum::HTTP_UNAUTHORIZED->value, $exception->getCode());
        $this->assertSame('Unauthorized', $exception->getMessage());
    }

    public function testIpAddressExceptionIsAnUnauthorizedExceptionWithStatus401(): void
    {
        $exception = new UnauthorizedIpAddressException(message: 'Not allowed from here');

        $this->expectException(UnauthorizedException::class);

        throw $exception;
    }

    public function testIpAddressExceptionKeepsCodeAndMessage(): void
    {
        $exception = new UnauthorizedIpAddressException(message: 'Not allowed from here');

        $this->assertSame(HttpStatusCodeEnum::HTTP_UNAUTHORIZED->value, $exception->getCode());
        $this->assertSame('Not allowed from here', $exception->getMessage());
    }
}
