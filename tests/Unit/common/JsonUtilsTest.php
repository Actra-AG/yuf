<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\JsonUtils;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

final class JsonUtilsTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-json-' . bin2hex(
            string: random_bytes(length: 8),
        );
        mkdir(directory: $this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->directory);
    }

    public function testConvertToJsonStringKeepsUnicode(): void
    {
        $this->assertSame('{"name":"Jörg","list":[1,2]}', JsonUtils::convertToJsonString(
            valueToConvert: ['name' => 'Jörg', 'list' => [1, 2]],
        ));
    }

    public function testConvertToJsonStringEscapesSlashes(): void
    {
        $this->assertSame('"a\/b"', JsonUtils::convertToJsonString(valueToConvert: 'a/b'));
    }

    public function testConvertToJsonStringThrowsForInvalidUtf8(): void
    {
        $this->expectException(JsonException::class);

        JsonUtils::convertToJsonString(valueToConvert: "\xB1\x31");
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function minifyProvider(): iterable
    {
        yield 'whitespace outside of strings' => ["{ \"a\" : 1 ,\n\t\"b\" : [ 1 , 2 ] }\r\n", '{"a":1,"b":[1,2]}'];
        yield 'whitespace inside of strings' => ['{"a": "x  y' . "\t" . 'z"}', '{"a":"x  y' . "\t" . 'z"}'];
        yield 'single line comment' => ["{\n// comment\n\"a\": 1 // trailing\n}", '{"a":1}'];
        yield 'multi line comment' => ["{ /* one\ntwo */ \"a\": 1 }", '{"a":1}'];
        yield 'comment markers inside of strings' => [
            '{"url": "http://example.com/*x*/", "b": "//"}',
            '{"url":"http://example.com/*x*/","b":"//"}',
        ];
        yield 'escaped quote inside of a string' => [
            '{"a": "say \\"hi\\" // not a comment"}',
            '{"a":"say \\"hi\\" // not a comment"}',
        ];
        yield 'escaped backslash before the closing quote' => [
            '{"a": "x\\\\" , "b": 1 // c' . "\n}",
            '{"a":"x\\\\","b":1}',
        ];
        yield 'comment inside of a comment' => ['{ /* // */ "a": 1 }', '{"a":1}'];
        yield 'line comment inside of a block comment' => ["{ /* a\n// b\n*/ \"a\": 1 }", '{"a":1}'];
        yield 'single slash is kept' => ['{"a": 1 / 2}', '{"a":1/2}'];
        yield 'comment at the end without newline' => ['{"a": 1} // end', '{"a":1}'];
        yield 'already minified' => ['{"a":[1,2,{"b":null}]}', '{"a":[1,2,{"b":null}]}'];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('minifyProvider')]
    public function testMinify(string $json, string $expected): void
    {
        $this->assertSame($expected, JsonUtils::minify(jsonString: $json));
    }

    public function testDecodeJsonStringReturnsAnObject(): void
    {
        $result = JsonUtils::decodeJsonString(jsonString: '{"a":{"b":1}}', returnAssociativeArray: false);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertEquals((object) ['b' => 1], $result->a);
    }

    public function testDecodeJsonStringReturnsAnArray(): void
    {
        $this->assertSame(
            ['a' => ['b' => 1]],
            JsonUtils::decodeJsonString(jsonString: '{"a":{"b":1}}', returnAssociativeArray: true),
        );
    }

    public function testDecodeJsonStringReturnsAListAsArray(): void
    {
        $this->assertSame([1, 2], JsonUtils::decodeJsonString(jsonString: '[1,2]', returnAssociativeArray: false));
    }

    public function testDecodeJsonStringKeepsBigIntegersAsString(): void
    {
        $this->assertSame(
            ['id' => '12345678901234567890'],
            JsonUtils::decodeJsonString(jsonString: '{"id":12345678901234567890}', returnAssociativeArray: true),
        );
    }

    public function testDecodeJsonStringThrowsForInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        JsonUtils::decodeJsonString(jsonString: '{"a":', returnAssociativeArray: true);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function noContainerProvider(): iterable
    {
        yield 'string' => ['"text"'];
        yield 'number' => ['5'];
        yield 'null' => ['null'];
        yield 'boolean' => ['true'];
    }

    #[DataProvider('noContainerProvider')]
    public function testDecodeJsonStringThrowsForAScalar(string $json): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/object or an array/');

        JsonUtils::decodeJsonString(jsonString: $json, returnAssociativeArray: true);
    }

    public function testDecodeFileRemovesCommentsOfAFileWithComments(): void
    {
        $path = $this->writeFile(
            name: 'commented.json',
            content: "{\n  // the name\n  \"name\": \"yuf\" /* x */\n}\n",
        );

        $this->assertSame(
            ['name' => 'yuf'],
            JsonUtils::decodeFile(filePath: $path, isMinified: false, returnAssociativeArray: true),
        );
    }

    public function testDecodeFileReturnsAnObjectByDefault(): void
    {
        $path = $this->writeFile(name: 'plain.json', content: '{"name":"yuf"}');

        $result = JsonUtils::decodeFile(filePath: $path, isMinified: true);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('yuf', $result->name);
    }

    public function testDecodeFileOfAnEmptyObject(): void
    {
        $path = $this->writeFile(name: 'empty.json', content: '{}');

        $this->assertSame([], JsonUtils::decodeFile(filePath: $path, isMinified: false, returnAssociativeArray: true));
    }

    public function testDecodeFileOfAMinifiedFileKeepsCommentMarkersInStrings(): void
    {
        $path = $this->writeFile(name: 'url.json', content: '{"url":"http://example.com"}');

        $this->assertSame(
            ['url' => 'http://example.com'],
            JsonUtils::decodeFile(filePath: $path, isMinified: true, returnAssociativeArray: true),
        );
    }

    public function testDecodeFileThrowsForAMissingFile(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'missing.json';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote(str: $path, delimiter: '/') . '/');

        JsonUtils::decodeFile(filePath: $path, isMinified: true);
    }

    public function testDecodeFileThrowsForADirectory(): void
    {
        $this->expectException(RuntimeException::class);

        JsonUtils::decodeFile(filePath: $this->directory, isMinified: true);
    }

    private function writeFile(string $name, string $content): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents(filename: $path, data: $content);

        return $path;
    }
}
