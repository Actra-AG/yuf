<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\StringUtils;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StringUtilsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function afterFirstProvider(): iterable
    {
        yield 'first of several' => ['a.b.c', '.', 'b.c'];
        yield 'not found' => ['abc', '.', ''];
        yield 'multibyte' => ['äö.üß', '.', 'üß'];
        yield 'at the end' => ['abc.', '.', ''];
    }

    #[DataProvider('afterFirstProvider')]
    public function testAfterFirst(string $string, string $after, string $expected): void
    {
        $this->assertSame($expected, StringUtils::afterFirst(string: $string, after: $after));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function beforeFirstProvider(): iterable
    {
        yield 'first of several' => ['a.b.c', '.', 'a'];
        yield 'not found keeps the string' => ['abc', '.', 'abc'];
        yield 'multibyte' => ['äö.üß', '.', 'äö'];
        yield 'at the start' => ['.abc', '.', ''];
    }

    #[DataProvider('beforeFirstProvider')]
    public function testBeforeFirst(string $string, string $before, string $expected): void
    {
        $this->assertSame($expected, StringUtils::beforeFirst(string: $string, before: $before));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function beforeLastProvider(): iterable
    {
        yield 'last of several' => ['a.b.c', '.', 'a.b'];
        yield 'not found keeps the string' => ['abc', '.', 'abc'];
        yield 'multibyte' => ['ä.ö.ü', '.', 'ä.ö'];
    }

    #[DataProvider('beforeLastProvider')]
    public function testBeforeLast(string $string, string $before, string $expected): void
    {
        $this->assertSame($expected, StringUtils::beforeLast(string: $string, before: $before));
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function afterLastProvider(): iterable
    {
        yield 'last of several' => ['a.b.c', '.', 'c'];
        yield 'not found is null' => ['abc', '.', null];
        yield 'at the end' => ['abc.', '.', ''];
        yield 'multibyte' => ['ä.ö.ü', '.', 'ü'];
    }

    #[DataProvider('afterLastProvider')]
    public function testAfterLast(string $string, string $after, ?string $expected): void
    {
        $this->assertSame($expected, StringUtils::afterLast(string: $string, after: $after));
    }

    /**
     * @return iterable<string, array{string, string, string, ?string}>
     */
    public static function betweenProvider(): iterable
    {
        yield 'brackets' => ['a[b]c', '[', ']', 'b'];
        yield 'up to the last end' => ['a[b]c]d', '[', ']', 'b]c'];
        yield 'empty content' => ['a[]c', '[', ']', ''];
        yield 'end not found' => ['a[bc', '[', ']', null];
        yield 'start not found' => ['abc', '[', ']', null];
        yield 'start not found, end present' => ['abc]', '[', ']', null];
        yield 'multibyte' => ['ä«ö»ü', '«', '»', 'ö'];
    }

    #[DataProvider('betweenProvider')]
    public function testBetween(string $string, string $start, string $end, ?string $expected): void
    {
        $this->assertSame($expected, StringUtils::between(string: $string, start: $start, end: $end));
    }

    public function testInsertBeforeLastInsertsBeforeTheLastOccurrence(): void
    {
        $this->assertSame(
            'a.bX.c',
            StringUtils::insertBeforeLast(string: 'a.b.c', beforeLast: '.', newString: 'X'),
        );
    }

    public function testInsertBeforeLastKeepsTheStringWithoutOccurrence(): void
    {
        $this->assertSame(
            'abc',
            StringUtils::insertBeforeLast(string: 'abc', beforeLast: '.', newString: 'X'),
        );
    }

    public function testBreakUpKeepsAShortSentence(): void
    {
        $this->assertSame('one two', StringUtils::breakUp(sentence: 'one two', atIndex: 10));
    }

    public function testBreakUpCutsAtTheLastSpaceBeforeTheIndex(): void
    {
        $this->assertSame('one two', StringUtils::breakUp(sentence: 'one two three four', atIndex: 10));
    }

    public function testBreakUpCutsAtTheGivenIndexNotAtFifty(): void
    {
        $sentence = str_repeat(string: 'word ', times: 20);

        $this->assertSame('word word word', StringUtils::breakUp(sentence: $sentence, atIndex: 16));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function tokenizeProvider(): iterable
    {
        yield 'several delimiters' => ['a,b;;c', ',;', ['a', 'b', 'c']];
        yield 'leading and trailing delimiters' => [',a,', ',', ['a']];
        yield 'empty string' => ['', ',', []];
        yield 'only delimiters' => [',,;', ',;', []];
        yield 'no delimiter found' => ['abc', ',', ['abc']];
        yield 'without delimiters' => ['abc', '', ['abc']];
        yield 'zero is a token' => ['0,1', ',', ['0', '1']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('tokenizeProvider')]
    public function testTokenize(string $string, string $delimiters, array $expected): void
    {
        $this->assertSame(
            $expected,
            StringUtils::tokenize(string: $string, delimiters: $delimiters),
        );
    }

    public function testExplodeWithOneToken(): void
    {
        $this->assertSame(['a', 'b'], StringUtils::explode(separators: ',', string: 'a,b'));
    }

    public function testExplodeWithSeveralTokens(): void
    {
        $this->assertSame(['a', 'b', 'c'], StringUtils::explode(separators: [',', ';'], string: 'a,b;c'));
    }

    public function testExplodeWithoutTokensInTheListKeepsTheString(): void
    {
        $this->assertSame(['a,b'], StringUtils::explode(separators: [], string: 'a,b'));
    }

    public function testExplodeRejectsAnEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StringUtils::explode(separators: '', string: 'a,b');
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function urlifyProvider(): iterable
    {
        yield 'spaces and case' => ['Hello World', '-', 0, 'hello-world'];
        yield 'special characters' => ['a & b / c', '-', 0, 'a-b-c'];
        yield 'dots are kept' => ['Report.2026.PDF', '-', 0, 'report.2026.pdf'];
        yield 'repeated and surrounding dashes' => ['--a---b--', '-', 0, 'a-b'];
        yield 'own separator' => ['Hello World', '_', 0, 'hello_world'];
        yield 'maximum length' => ['Hello World', '-', 5, 'hello'];
        yield 'empty string' => ['', '-', 0, 'unbenannt'];
        yield 'only special characters' => ['&&&', '-', 0, 'unbenannt'];
    }

    #[DataProvider('urlifyProvider')]
    public function testUrlify(string $string, string $separator, int $maxLength, string $expected): void
    {
        $this->assertSame(
            $expected,
            StringUtils::urlify(string: $string, separator: $separator, maxLength: $maxLength),
        );
    }

    public function testEmptyToNull(): void
    {
        $this->assertNull(StringUtils::emptyToNull(string: ''));
        $this->assertSame('0', StringUtils::emptyToNull(string: '0'));
        $this->assertSame(' ', StringUtils::emptyToNull(string: ' '));
    }

    public function testUtf8ToPunycodeEmail(): void
    {
        $this->assertSame(
            'jörg@xn--bcher-kva.example',
            StringUtils::utf8ToPunycodeEmail(email: 'jörg@bücher.example'),
        );
    }

    public function testPunycodeToUtf8Email(): void
    {
        $this->assertSame(
            'jörg@bücher.example',
            StringUtils::punycodeToUtf8Email(email: 'jörg@xn--bcher-kva.example'),
        );
    }

    public function testPunycodeKeepsAtCharactersOfTheLocalPart(): void
    {
        $this->assertSame(
            '"a@b"@xn--bcher-kva.example',
            StringUtils::utf8ToPunycodeEmail(email: '"a@b"@bücher.example'),
        );
    }

    public function testPunycodeRejectsAnInvalidDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StringUtils::utf8ToPunycodeEmail(email: 'jörg@');
    }

    public function testUtf8FromPunycodeRejectsAnInvalidDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StringUtils::punycodeToUtf8Email(email: 'jörg@');
    }

    /**
     * @return iterable<string, array{int|float, string}>
     */
    public static function formatBytesProvider(): iterable
    {
        yield 'zero' => [0, '0 B'];
        yield 'bytes' => [1023, '1023 B'];
        yield 'one kilobyte' => [1024, '1 KB'];
        yield 'rounded' => [1536, '1.5 KB'];
        yield 'two decimals' => [1024 * 1024 + 10240, '1.01 MB'];
        yield 'megabyte' => [1024 ** 2, '1 MB'];
        yield 'gigabyte' => [1024 ** 3, '1 GB'];
        yield 'terabyte' => [1024 ** 4, '1 TB'];
        yield 'more than terabytes stays terabytes' => [1024 ** 5, '1024 TB'];
        yield 'negative is zero' => [-5, '0 B'];
        yield 'fraction of a byte' => [0.5, '0.5 B'];
        yield 'float' => [2048.0, '2 KB'];
    }

    #[DataProvider('formatBytesProvider')]
    public function testFormatBytes(int|float $bytes, string $expected): void
    {
        $this->assertSame($expected, StringUtils::formatBytes(bytes: $bytes));
    }

    public function testFormatBytesWithPrecision(): void
    {
        $this->assertSame('1.5 KB', StringUtils::formatBytes(bytes: 1536, precision: 1));
        $this->assertSame('1.17 KB', StringUtils::formatBytes(bytes: 1200, precision: 2));
        $this->assertSame('1 KB', StringUtils::formatBytes(bytes: 1200, precision: 0));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function randomStringLengthProvider(): iterable
    {
        yield 'zero is one' => [0];
        yield 'one' => [1];
        yield 'two' => [2];
        yield 'three' => [3];
        yield 'four' => [4];
        yield 'ten' => [10];
        yield 'long' => [200];
    }

    #[DataProvider('randomStringLengthProvider')]
    public function testRandomStringHasTheRequiredLength(int $length): void
    {
        $this->assertSame(max($length, 1), strlen(StringUtils::randomString(
            requiredStringLength: $length,
            noSpecialChars: false,
        )));
    }

    public function testRandomStringWithoutSpecialCharsHasOnlyLettersAndDigits(): void
    {
        $string = StringUtils::randomString(requiredStringLength: 500, noSpecialChars: true);

        $this->assertMatchesRegularExpression('/^[a-zA-Z2-9]{500}$/D', $string);
        $this->assertDoesNotMatchRegularExpression('/[ilILOo01]/', $string);
    }

    public function testRandomStringContainsEveryCharacterGroup(): void
    {
        for ($run = 0; $run < 50; $run++) {
            $string = StringUtils::randomString(requiredStringLength: 4, noSpecialChars: false);

            $this->assertMatchesRegularExpression('/[a-z]/', $string);
            $this->assertMatchesRegularExpression('/[A-Z]/', $string);
            $this->assertMatchesRegularExpression('/[2-9]/', $string);
            $this->assertMatchesRegularExpression('/[!@#$%&*?]/', $string);
        }
    }

    public function testRandomStringsDiffer(): void
    {
        $this->assertNotSame(
            StringUtils::randomString(requiredStringLength: 32, noSpecialChars: false),
            StringUtils::randomString(requiredStringLength: 32, noSpecialChars: false),
        );
    }

    public function testGenerateSaltHasTheLength(): void
    {
        $this->assertSame(16, mb_strlen(string: StringUtils::generateSalt()));
        $this->assertSame(5, mb_strlen(string: StringUtils::generateSalt(length: 5)));
        $this->assertSame('', StringUtils::generateSalt(length: 0));
    }
}
