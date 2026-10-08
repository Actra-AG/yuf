<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\exception\ErrorKindEnum;
use actra\yuf\exception\NotFoundException;
use actra\yuf\exception\PhpException;
use actra\yuf\exception\UnauthorizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use TypeError;

final class ErrorKindEnumTest extends TestCase
{
    public function testKindOfThrowable(): void
    {
        $this->assertSame(ErrorKindEnum::NOT_FOUND, ErrorKindEnum::fromThrowable(throwable: new NotFoundException()));
        $this->assertSame(
            ErrorKindEnum::UNAUTHORIZED,
            ErrorKindEnum::fromThrowable(throwable: new UnauthorizedException()),
        );
        $this->assertSame(
            ErrorKindEnum::UNAUTHORIZED,
            ErrorKindEnum::fromThrowable(throwable: new UnauthorizedIpAddressException()),
        );
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function internalErrorProvider(): iterable
    {
        yield 'runtime exception' => [new RuntimeException()];
        yield 'php error exception' => [new PhpException(message: '', code: 0, file: '', line: 0)];
        yield 'error' => [new TypeError()];
    }

    #[DataProvider('internalErrorProvider')]
    public function testEverythingElseIsAnInternalError(Throwable $throwable): void
    {
        $this->assertSame(ErrorKindEnum::INTERNAL_ERROR, ErrorKindEnum::fromThrowable(throwable: $throwable));
    }

    public function testStatusPageTitleAndPublicMessageOfEachKind(): void
    {
        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, ErrorKindEnum::NOT_FOUND->getHttpStatusCode());
        $this->assertSame('notFound.html', ErrorKindEnum::NOT_FOUND->getHtmlFileName());
        $this->assertSame('Page not found', ErrorKindEnum::NOT_FOUND->getTitle());
        $this->assertSame('Not Found', ErrorKindEnum::NOT_FOUND->getPublicMessage());
        $this->assertSame(HttpStatusCodeEnum::HTTP_UNAUTHORIZED, ErrorKindEnum::UNAUTHORIZED->getHttpStatusCode());
        $this->assertSame('unauthorized.html', ErrorKindEnum::UNAUTHORIZED->getHtmlFileName());
        $this->assertSame('Unauthorized', ErrorKindEnum::UNAUTHORIZED->getPublicMessage());
        $this->assertSame(
            HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR,
            ErrorKindEnum::INTERNAL_ERROR->getHttpStatusCode(),
        );
        $this->assertSame('default.html', ErrorKindEnum::INTERNAL_ERROR->getHtmlFileName());
        $this->assertSame('Internal Server Error', ErrorKindEnum::INTERNAL_ERROR->getPublicMessage());
    }
}
