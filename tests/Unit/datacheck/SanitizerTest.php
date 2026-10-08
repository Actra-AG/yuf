<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck;

use actra\yuf\datacheck\Sanitizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SanitizerTest extends TestCase
{
    public function testDomainDelegatesToTheDomainSanitizer(): void
    {
        $this->assertSame('example.com', Sanitizer::domain(input: 'HTTPS://www.Example.com/'));
    }

    public function testTrimmedStringTrimsWhitespace(): void
    {
        $this->assertSame('a b', Sanitizer::trimmedString(input: " \t a b\n "));
    }

    public function testTrimmedStringOfNumbersAndBooleans(): void
    {
        $this->assertSame('5', Sanitizer::trimmedString(input: 5));
        $this->assertSame('-1.5', Sanitizer::trimmedString(input: -1.5));
        $this->assertSame('1', Sanitizer::trimmedString(input: true));
        $this->assertSame('', Sanitizer::trimmedString(input: false));
    }

    public function testTrimmedStringOfNullIsEmpty(): void
    {
        $this->assertSame('', Sanitizer::trimmedString(input: null));
    }

    public function testIntegerDelegatesToTheIntegerSanitizer(): void
    {
        $this->assertSame(42, Sanitizer::integer(input: ' 42 '));
    }

    public function testIntegerThrowsForNoInteger(): void
    {
        $this->expectException(RuntimeException::class);

        Sanitizer::integer(input: 'abc');
    }

    public function testFloatDelegatesToTheFloatSanitizer(): void
    {
        $this->assertSame(1.5, Sanitizer::float(input: '1,5'));
    }

    public function testFloatThrowsForNoFloat(): void
    {
        $this->expectException(RuntimeException::class);

        Sanitizer::float(input: 'abc');
    }
}
