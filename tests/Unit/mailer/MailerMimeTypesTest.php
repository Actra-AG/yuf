<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerMimeTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailerMimeTypesTest extends TestCase
{
    public function testTypeOfAnExtensionIgnoresTheCase(): void
    {
        $this->assertSame('image/png', MailerMimeTypes::getByExtension(extension: 'PNG'));
        $this->assertSame('application/octet-stream', MailerMimeTypes::getByExtension(extension: 'nope'));
        $this->assertSame('application/octet-stream', MailerMimeTypes::getByExtension(extension: ''));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function typeProvider(): iterable
    {
        yield 'text' => ['text/plain', true];
        yield 'with plus and dot' => ['application/vnd.api+json', true];
        yield 'upper case' => ['Image/PNG', true];
        yield 'with parameter' => ['text/plain; charset=utf-8', false];
        yield 'without subtype' => ['text', false];
        yield 'empty' => ['', false];
        yield 'space' => ['text/ plain', false];
        yield 'line break' => ["text/plain\r\nBcc: e@example.com", false];
        yield 'trailing line break' => ["text/plain\n", false];
        yield 'quote' => ['text/"plain"', false];
        yield 'semicolon' => ['text/plain;', false];
        yield 'two slashes' => ['text/plain/x', false];
    }

    #[DataProvider('typeProvider')]
    public function testValidType(string $type, bool $expected): void
    {
        $this->assertSame($expected, MailerMimeTypes::isValidType(type: $type));
    }
}
