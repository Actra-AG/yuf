<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\exception;

use actra\yuf\core\ContentType;
use actra\yuf\exception\ErrorOutputFormatEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorOutputFormatEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ErrorOutputFormatEnum}>
     */
    public static function contentTypeProvider(): iterable
    {
        yield 'json' => ['json', ErrorOutputFormatEnum::JSON];
        yield 'txt' => ['txt', ErrorOutputFormatEnum::TEXT];
        yield 'csv' => ['csv', ErrorOutputFormatEnum::TEXT];
        yield 'html' => ['html', ErrorOutputFormatEnum::HTML];
        yield 'xml' => ['xml', ErrorOutputFormatEnum::HTML];
        yield 'js' => ['js', ErrorOutputFormatEnum::HTML];
        yield 'image' => ['png', ErrorOutputFormatEnum::HTML];
    }

    #[DataProvider('contentTypeProvider')]
    public function testFormatOfContentType(string $extension, ErrorOutputFormatEnum $expectedFormat): void
    {
        $contentType = ContentType::createFromFileExtension(extension: $extension);

        $this->assertSame($expectedFormat, ErrorOutputFormatEnum::fromContentType(contentType: $contentType));
    }
}
