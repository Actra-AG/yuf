<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\DomainValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function validateProvider(): iterable
    {
        yield 'domain' => ['example.com', true];
        yield 'upper case' => ['EXAMPLE.CH', true];
        yield 'mixed case' => ['Example.COM', true];
        yield 'two letter name' => ['ab.ch', true];
        yield 'subdomains' => ['a.b.c.example.com', true];
        yield 'hyphen inside' => ['my-example.com', true];
        yield 'starting with a digit' => ['1example.com', true];
        yield 'umlaut' => ['münchen.de', true];
        yield 'punycode' => ['xn--mnchen-3ya.de', true];
        yield 'Japanese name' => ['例え.jp', true];
        yield 'label with 63 characters' => [str_repeat(string: 'a', times: 63) . '.com', true];
        yield 'Cyrillic name and TLD' => ['пример.рф', true];
        yield 'Cyrillic name in upper case and TLD' => ['ПРИМЕР.РФ', true];
        yield 'Cyrillic name with a generic TLD' => ['пример.com', true];
        yield 'a TLD alone' => ['academy', false];
        yield 'a TLD alone in upper case' => ['ACADEMY', false];
        yield 'a punycode TLD alone' => ['xn--p1ai', false];
        yield 'too short' => ['a.ch', false];
        yield 'empty' => ['', false];
        yield 'whitespace inside' => ['exa mple.com', false];
        yield 'no-break space inside' => ["exa\u{00A0}mple.com", false];
        yield 'ideographic space inside' => ["exa\u{3000}mple.com", false];
        yield 'surrounding space' => ['example.com ', false];
        yield 'trailing line break' => ["example.com\n", false];
        yield 'trailing dot' => ['example.com.', false];
        yield 'leading dot' => ['.example.com', false];
        yield 'empty label' => ['example..com', false];
        yield 'leading hyphen' => ['-example.com', false];
        yield 'trailing hyphen' => ['example-.com', false];
        yield 'underscore' => ['ex_ample.com', false];
        yield 'unknown TLD' => ['example.invalid', false];
        yield 'one letter TLD' => ['example.c', false];
        yield 'numeric TLD' => ['example.123', false];
        yield 'IPv4 address' => ['127.0.0.1', false];
        yield 'label with 64 characters' => [str_repeat(string: 'a', times: 64) . '.com', false];
        yield 'more than 253 characters' => ['a.com' . str_repeat(string: '.a', times: 130), false];
        yield 'unicode TLD is not a listed name' => ['例え.テスト', false];
    }

    #[DataProvider('validateProvider')]
    public function testValidate(string $input, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, DomainValidator::validate(input: $input));
    }
}
