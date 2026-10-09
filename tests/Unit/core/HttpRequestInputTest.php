<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The explicit query and post getters. Query and post data are never merged. Number getters are strict (design
 * section 5 of docs/plans/done/http-request/design.md): they accept a complete number only, not a prefix of one.
 */
final class HttpRequestInputTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function stringProvider(): iterable
    {
        yield 'plain' => ['abc', 'abc'];
        yield 'trimmed' => ["  abc \t\n", 'abc'];
        yield 'inner whitespace is kept' => [' a  b ', 'a  b'];
        yield 'empty string' => ['', ''];
        yield 'only whitespace' => ['   ', ''];
        yield 'zero string' => ['0', '0'];
        yield 'integer' => [42, '42'];
        yield 'negative integer' => [-7, '-7'];
        yield 'float' => [1.5, '1.5'];
        yield 'true' => [true, '1'];
        yield 'false' => [false, ''];
        yield 'null' => [null, null];
        yield 'array' => [['a'], null];
        yield 'empty array' => [[], null];
        yield 'unicode' => [" Zürich\u{00A0}", "Zürich\u{00A0}"];
    }

    #[DataProvider('stringProvider')]
    public function testQueryString(mixed $value, ?string $expected): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['key' => $value]);

        $this->assertSame($expected, $httpRequest->getQueryString(name: 'key'));
    }

    #[DataProvider('stringProvider')]
    public function testPostString(mixed $value, ?string $expected): void
    {
        $httpRequest = HttpRequestFactory::create(postParameters: ['key' => $value]);

        $this->assertSame($expected, $httpRequest->getPostString(name: 'key'));
    }

    public function testMissingStringIsNull(): void
    {
        $httpRequest = HttpRequestFactory::create();

        $this->assertNull($httpRequest->getQueryString(name: 'missing'));
        $this->assertNull($httpRequest->getPostString(name: 'missing'));
    }

    public function testNamesAreCaseSensitive(): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['Key' => 'a']);

        $this->assertNull($httpRequest->getQueryString(name: 'key'));
    }

    public function testNumericNamesAreFoundUnderTheirOwnName(): void
    {
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['5' => 'five'],
            postParameters: ['5' => 'post five'],
        );

        $this->assertSame('five', $httpRequest->getQueryString(name: '5'));
        $this->assertNull($httpRequest->getQueryString(name: '0'));
        $this->assertSame('post five', $httpRequest->getPostString(name: '5'));
    }

    public function testQueryAndPostAreNotMerged(): void
    {
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['a' => 'from query', 'key' => 'from query'],
            postParameters: ['b' => 'from post', 'key' => 'from post'],
        );

        $this->assertSame('from query', $httpRequest->getQueryString(name: 'a'));
        $this->assertNull($httpRequest->getPostString(name: 'a'));
        $this->assertSame('from post', $httpRequest->getPostString(name: 'b'));
        $this->assertNull($httpRequest->getQueryString(name: 'b'));
        $this->assertSame('from query', $httpRequest->getQueryString(name: 'key'));
        $this->assertSame('from post', $httpRequest->getPostString(name: 'key'));
    }

    public function testHasValueOfTheOwnSourceOnly(): void
    {
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['q' => '', 'list' => []],
            postParameters: ['p' => 'x'],
        );

        $this->assertTrue($httpRequest->hasQueryValue(name: 'q'));
        $this->assertTrue($httpRequest->hasQueryValue(name: 'list'));
        $this->assertFalse($httpRequest->hasQueryValue(name: 'p'));
        $this->assertTrue($httpRequest->hasPostValue(name: 'p'));
        $this->assertFalse($httpRequest->hasPostValue(name: 'q'));
    }

    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function integerProvider(): iterable
    {
        yield 'plain' => ['12', 12];
        yield 'zero' => ['0', 0];
        yield 'trailing garbage' => ['12abc', null];
        yield 'leading garbage' => ['abc12', null];
        yield 'empty string' => ['', null];
        yield 'whitespace only' => ['  ', null];
        yield 'surrounding whitespace is trimmed' => [' 12 ', 12];
        yield 'decimal string' => ['1.5', null];
        yield 'negative decimal string' => ['-1.9', null];
        yield 'decimal comma' => ['1,5', null];
        yield 'exponent notation' => ['1e3', null];
        yield 'negative' => ['-5', -5];
        yield 'plus sign' => ['+5', null];
        yield 'hexadecimal' => ['0x1A', null];
        yield 'leading zeros' => ['007', 7];
        yield 'maximum' => [(string) PHP_INT_MAX, PHP_INT_MAX];
        yield 'minimum' => [(string) PHP_INT_MIN, PHP_INT_MIN];
        yield 'overflow' => ['99999999999999999999', null];
        yield 'negative overflow' => ['-99999999999999999999', null];
        yield 'integer value' => [12, 12];
        yield 'float value' => [2.9, null];
        yield 'true' => [true, 1];
        yield 'false' => [false, null];
        yield 'array' => [['1'], null];
        yield 'null' => [null, null];
    }

    #[DataProvider('integerProvider')]
    public function testQueryInteger(mixed $value, ?int $expected): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['key' => $value]);

        $this->assertSame($expected, $httpRequest->getQueryInteger(name: 'key'));
    }

    #[DataProvider('integerProvider')]
    public function testPostInteger(mixed $value, ?int $expected): void
    {
        $httpRequest = HttpRequestFactory::create(postParameters: ['key' => $value]);

        $this->assertSame($expected, $httpRequest->getPostInteger(name: 'key'));
    }

    public function testMissingIntegerIsNull(): void
    {
        $httpRequest = HttpRequestFactory::create();

        $this->assertNull($httpRequest->getQueryInteger(name: 'missing'));
        $this->assertNull($httpRequest->getPostInteger(name: 'missing'));
    }

    /**
     * @return iterable<string, array{mixed, float|null}>
     */
    public static function floatProvider(): iterable
    {
        yield 'plain' => ['1.5', 1.5];
        yield 'integer string' => ['12', 12.0];
        yield 'trailing garbage' => ['1.5abc', null];
        yield 'empty string' => ['', null];
        yield 'decimal comma' => ['1,5', null];
        yield 'exponent notation' => ['1e3', 1000.0];
        yield 'negative exponent' => ['25E-1', 2.5];
        yield 'negative' => ['-0.25', -0.25];
        yield 'plus sign' => ['+1.5', null];
        yield 'no digit before the point' => ['.5', null];
        yield 'no digit after the point' => ['1.', null];
        yield 'not a number' => ['abc', null];
        yield 'infinity' => ['INF', null];
        yield 'not a number constant' => ['NAN', null];
        yield 'exponent overflow' => ['1e999', null];
        yield 'surrounding whitespace is trimmed' => [' 1.5 ', 1.5];
        yield 'integer value' => [3, 3.0];
        yield 'float value' => [1.25, 1.25];
        yield 'true' => [true, 1.0];
        yield 'false' => [false, null];
        yield 'array' => [['1.5'], null];
        yield 'null' => [null, null];
    }

    #[DataProvider('floatProvider')]
    public function testQueryFloat(mixed $value, ?float $expected): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['key' => $value]);

        $this->assertSame($expected, $httpRequest->getQueryFloat(name: 'key'));
    }

    #[DataProvider('floatProvider')]
    public function testPostFloat(mixed $value, ?float $expected): void
    {
        $httpRequest = HttpRequestFactory::create(postParameters: ['key' => $value]);

        $this->assertSame($expected, $httpRequest->getPostFloat(name: 'key'));
    }

    public function testMissingFloatIsNull(): void
    {
        $httpRequest = HttpRequestFactory::create();

        $this->assertNull($httpRequest->getQueryFloat(name: 'missing'));
        $this->assertNull($httpRequest->getPostFloat(name: 'missing'));
    }

    public function testArrayKeepsKeysAndIsNotTrimmed(): void
    {
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['key' => ['x' => ' 1 ', 'y' => '2', 'nested' => ['a']]],
            postParameters: ['key' => ['a', ' b ']],
        );

        $this->assertSame(['x' => ' 1 ', 'y' => '2', 'nested' => ['a']], $httpRequest->getQueryArray(name: 'key'));
        $this->assertSame(['a', ' b '], $httpRequest->getPostArray(name: 'key'));
    }

    public function testEmptyArray(): void
    {
        $this->assertSame([], HttpRequestFactory::create(queryParameters: ['key' => []])->getQueryArray(name: 'key'));
    }

    public function testArrayOfAScalarIsNull(): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['key' => 'abc'], postParameters: ['key' => 'abc']);

        $this->assertNull($httpRequest->getQueryArray(name: 'key'));
        $this->assertNull($httpRequest->getPostArray(name: 'key'));
    }

    public function testMissingArrayIsNull(): void
    {
        $httpRequest = HttpRequestFactory::create();

        $this->assertNull($httpRequest->getQueryArray(name: 'missing'));
        $this->assertNull($httpRequest->getPostArray(name: 'missing'));
    }

    public function testStringOfAnArrayIsNull(): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['key' => ['a']]);

        $this->assertNull($httpRequest->getQueryString(name: 'key'));
    }

    public function testAllParametersAreAvailableAsSent(): void
    {
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['a' => '1'],
            postParameters: ['b' => ['2']],
            uploadedFiles: ['f' => ['name' => 'x']],
        );

        $this->assertSame(['a' => '1'], $httpRequest->getQueryParameters());
        $this->assertSame(['b' => ['2']], $httpRequest->getPostParameters());
        $this->assertSame(['f' => ['name' => 'x']], $httpRequest->getRawFiles());
    }
}
