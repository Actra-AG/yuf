<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberNormalizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function normalizeProvider(): iterable
    {
        yield 'digits with separators' => ['044 668 18 00', '0446681800'];
        yield 'brackets, slashes and hyphens' => ['(044) 668/18-00', '0446681800'];
        yield 'full-width digits' => ["\u{FF10}\u{FF14}\u{FF14}", '044'];
        yield 'Arabic-Indic digits' => ["\u{0660}\u{0664}\u{0664}", '044'];
        yield 'extended Arabic-Indic digits' => ["\u{06F0}\u{06F4}\u{06F4}", '044'];
        yield 'Mongolian digits' => ["\u{1810}\u{1814}\u{1814}", '044'];
        yield 'two letters are dropped' => ['04 ab 4', '044'];
        yield 'three letters are keypad digits' => ['0800 CALLME', '0800225563'];
        yield 'lower case letters' => ['0800 callme', '0800225563'];
        yield 'letters of the keypad' => ['abcdefghijklmnopqrstuvwxyz', '22233344455566677778889999'];
        yield 'letters with other characters' => ['1-800-FLOWERS!', '18003569377'];
        yield 'empty' => ['', ''];
        yield 'no digits' => ['+-/', ''];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $number, string $expected): void
    {
        $this->assertSame($expected, PhoneNumberNormalizer::normalize(number: $number));
    }

    public function testNormalizeDigitsKeepsLettersOut(): void
    {
        $this->assertSame('0448', PhoneNumberNormalizer::normalizeDigits(number: '044 abc 8'));
    }
}
