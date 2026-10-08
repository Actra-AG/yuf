<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\exception\NotFoundException;
use PHPUnit\Framework\TestCase;

final class NotFoundExceptionTest extends TestCase
{
    public function testDefaultsAreTheStatusTextAndCode404(): void
    {
        $exception = new NotFoundException();

        $this->assertSame('Not Found', $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }

    public function testEmptyMessageIsReplacedByTheStatusText(): void
    {
        $this->assertSame('Not Found', new NotFoundException(message: '')->getMessage());
    }

    public function testMessageIsKept(): void
    {
        $exception = new NotFoundException(message: 'Path variable 1 is missing');

        $this->assertSame('Path variable 1 is missing', $exception->getMessage());
    }

    public function testCodeIsTheValueOfTheStatus(): void
    {
        $exception = new NotFoundException(message: 'Gone', code: HttpStatusCodeEnum::HTTP_GONE);

        $this->assertSame(410, $exception->getCode());
    }
}
