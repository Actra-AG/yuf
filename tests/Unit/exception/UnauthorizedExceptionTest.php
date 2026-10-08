<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

final class UnauthorizedExceptionTest extends TestCase
{
    public function testDefaultsAreTheStatusTextAndCode401(): void
    {
        $exception = new UnauthorizedException();

        $this->assertSame('Unauthorized', $exception->getMessage());
        $this->assertSame(401, $exception->getCode());
    }

    public function testMessageIsKept(): void
    {
        $this->assertSame('Missing kid', new UnauthorizedException(message: 'Missing kid')->getMessage());
    }

    public function testCodeIsTheValueOfTheStatus(): void
    {
        $exception = new UnauthorizedException(code: HttpStatusCodeEnum::HTTP_FORBIDDEN);

        $this->assertSame(403, $exception->getCode());
    }
}
