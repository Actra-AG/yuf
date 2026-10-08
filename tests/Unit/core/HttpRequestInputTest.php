<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the static HttpRequest before the redesign (docs/http-request/plan.md, step 1).
 *
 * The merged input of $_GET and $_POST is cached statically for the whole process, so every test runs in its own
 * process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HttpRequestInputTest extends TestCase
{
    /** @var array<mixed> */
    private array $getBackup = [];
    /** @var array<mixed> */
    private array $postBackup = [];

    #[Override]
    protected function setUp(): void
    {
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $_GET = [];
        $_POST = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
    }

    /**
     * @return iterable<string, array{mixed, string|null}>
     */
    public static function inputStringProvider(): iterable
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

    #[DataProvider('inputStringProvider')]
    public function testInputString(mixed $value, ?string $expected): void
    {
        $_GET['key'] = $value;

        $this->assertSame($expected, HttpRequest::getInputString(keyName: 'key'));
    }

    public function testMissingInputStringIsNull(): void
    {
        $this->assertNull(HttpRequest::getInputString(keyName: 'missing'));
    }

    public function testInputKeysAreCaseSensitive(): void
    {
        $_GET['Key'] = 'a';

        $this->assertNull(HttpRequest::getInputString(keyName: 'key'));
    }

    public function testNumericKeysAreRenumberedByTheMerge(): void
    {
        $_GET['5'] = 'five';

        $this->assertNull(HttpRequest::getInputString(keyName: '5'));
        $this->assertSame('five', HttpRequest::getInputString(keyName: '0'));
    }

    public function testNumericKeysOfGetAndPostCollide(): void
    {
        $_GET['5'] = 'from get';
        $_POST['7'] = 'from post';

        $this->assertSame('from get', HttpRequest::getInputString(keyName: '0'));
        $this->assertSame('from post', HttpRequest::getInputString(keyName: '1'));
    }

    public function testPostWinsOverGet(): void
    {
        $_GET['key'] = 'from get';
        $_POST['key'] = 'from post';

        $this->assertSame('from post', HttpRequest::getInputString(keyName: 'key'));
    }

    public function testGetAndPostAreMerged(): void
    {
        $_GET['a'] = 'from get';
        $_POST['b'] = 'from post';

        $this->assertSame('from get', HttpRequest::getInputString(keyName: 'a'));
        $this->assertSame('from post', HttpRequest::getInputString(keyName: 'b'));
    }

    public function testPostArrayReplacesGetStringCompletely(): void
    {
        $_GET['key'] = 'from get';
        $_POST['key'] = ['from post'];

        $this->assertNull(HttpRequest::getInputString(keyName: 'key'));
        $this->assertSame(['from post'], HttpRequest::getInputArray(keyName: 'key'));
    }

    public function testInputIsCachedForTheWholeProcess(): void
    {
        $_GET['key'] = 'first';
        $this->assertSame('first', HttpRequest::getInputString(keyName: 'key'));

        $_GET['key'] = 'second';
        $_POST['other'] = 'new';

        $this->assertSame('first', HttpRequest::getInputString(keyName: 'key'));
        $this->assertNull(HttpRequest::getInputString(keyName: 'other'));
    }

    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function inputIntegerProvider(): iterable
    {
        yield 'plain' => ['12', 12];
        yield 'trailing garbage' => ['12abc', 12];
        yield 'leading garbage' => ['abc12', 0];
        yield 'empty string' => ['', 0];
        yield 'whitespace only' => ['  ', 0];
        yield 'surrounding whitespace' => [' 12 ', 12];
        yield 'decimal string is truncated' => ['1.5', 1];
        yield 'negative decimal string is truncated' => ['-1.9', -1];
        yield 'decimal comma' => ['1,5', 1];
        yield 'exponent notation' => ['1e3', 1000];
        yield 'negative' => ['-5', -5];
        yield 'plus sign' => ['+5', 5];
        yield 'hexadecimal is not parsed' => ['0x1A', 0];
        yield 'leading zeros' => ['007', 7];
        yield 'overflow saturates' => ['99999999999999999999', PHP_INT_MAX];
        yield 'true' => [true, 1];
        yield 'false' => [false, 0];
        yield 'float' => [2.9, 2];
        yield 'array' => [['1'], null];
        yield 'null' => [null, null];
    }

    #[DataProvider('inputIntegerProvider')]
    public function testInputInteger(mixed $value, ?int $expected): void
    {
        $_GET['key'] = $value;

        $this->assertSame($expected, HttpRequest::getInputInteger(keyName: 'key'));
    }

    public function testMissingInputIntegerIsNull(): void
    {
        $this->assertNull(HttpRequest::getInputInteger(keyName: 'missing'));
    }

    /**
     * @return iterable<string, array{mixed, float|null}>
     */
    public static function inputFloatProvider(): iterable
    {
        yield 'plain' => ['1.5', 1.5];
        yield 'integer string' => ['12', 12.0];
        yield 'trailing garbage' => ['1.5abc', 1.5];
        yield 'empty string' => ['', 0.0];
        yield 'decimal comma is cut' => ['1,5', 1.0];
        yield 'exponent notation' => ['1e3', 1000.0];
        yield 'negative' => ['-0.25', -0.25];
        yield 'not a number' => ['abc', 0.0];
        yield 'true' => [true, 1.0];
        yield 'false' => [false, 0.0];
        yield 'integer' => [3, 3.0];
        yield 'array' => [['1.5'], null];
        yield 'null' => [null, null];
    }

    #[DataProvider('inputFloatProvider')]
    public function testInputFloat(mixed $value, ?float $expected): void
    {
        $_GET['key'] = $value;

        $this->assertSame($expected, HttpRequest::getInputFloat(keyName: 'key'));
    }

    public function testMissingInputFloatIsNull(): void
    {
        $this->assertNull(HttpRequest::getInputFloat(keyName: 'missing'));
    }

    public function testInputArray(): void
    {
        $_POST['key'] = ['a', 'b', ' c ', ['nested']];

        $this->assertSame(['a', 'b', ' c ', ['nested']], HttpRequest::getInputArray(keyName: 'key'));
    }

    public function testInputArrayKeepsKeysAndDoesNotTrim(): void
    {
        $_GET['key'] = ['x' => ' 1 ', 'y' => '2'];

        $this->assertSame(['x' => ' 1 ', 'y' => '2'], HttpRequest::getInputArray(keyName: 'key'));
    }

    public function testEmptyInputArray(): void
    {
        $_GET['key'] = [];

        $this->assertSame([], HttpRequest::getInputArray(keyName: 'key'));
    }

    public function testInputArrayOfScalarIsNull(): void
    {
        $_GET['key'] = 'abc';

        $this->assertNull(HttpRequest::getInputArray(keyName: 'key'));
    }

    public function testMissingInputArrayIsNull(): void
    {
        $this->assertNull(HttpRequest::getInputArray(keyName: 'missing'));
    }

    public function testInputValueOfStringIsNotTrimmed(): void
    {
        $_GET['key'] = '  abc ';

        $this->assertSame('  abc ', HttpRequest::getInputValue(keyName: 'key'));
    }

    public function testInputValueOfArray(): void
    {
        $_GET['key'] = ['a'];

        $this->assertSame(['a'], HttpRequest::getInputValue(keyName: 'key'));
    }

    public function testMissingInputValueIsNull(): void
    {
        $this->assertNull(HttpRequest::getInputValue(keyName: 'missing'));
    }

    public function testExistingNullInputValueIsNull(): void
    {
        $_GET['key'] = null;

        $this->assertNull(HttpRequest::getInputValue(keyName: 'key'));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function scalarInputProvider(): iterable
    {
        yield 'string' => ['abc', true];
        yield 'empty string' => ['', true];
        yield 'integer' => [0, true];
        yield 'float' => [0.5, true];
        yield 'false' => [false, true];
        yield 'null' => [null, false];
        yield 'array' => [['a'], false];
        yield 'empty array' => [[], false];
    }

    #[DataProvider('scalarInputProvider')]
    public function testHasScalarInputValue(mixed $value, bool $expected): void
    {
        $_GET['key'] = $value;

        $this->assertSame($expected, HttpRequest::hasScalarInputValue(keyName: 'key'));
    }

    public function testMissingKeyIsNoScalarInputValue(): void
    {
        $this->assertFalse(HttpRequest::hasScalarInputValue(keyName: 'missing'));
    }
}
