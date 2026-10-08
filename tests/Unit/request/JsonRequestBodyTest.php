<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\request;

use actra\yuf\request\JsonRequestBody;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Written against JsonRequestBody::fromString() after reading the former get(), which parsed php://input and could
 * not be tested.
 */
final class JsonRequestBodyTest extends TestCase
{
    public function testEmptyStringIsEmptyObject(): void
    {
        $body = JsonRequestBody::fromString(json: '');

        $this->assertEquals(new stdClass(), $body->data);
        $this->assertNull($body->getOptionalString(keyName: 'a'));
    }

    public function testInvalidJsonThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('JSON error: Syntax error');
        JsonRequestBody::fromString(json: '{"a":');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function noObjectProvider(): iterable
    {
        yield 'list' => ['[1, 2]', 'array'];
        yield 'string' => ['"text"', 'string'];
        yield 'number' => ['5', 'int'];
        yield 'null' => ['null', 'null'];
    }

    #[DataProvider('noObjectProvider')]
    public function testJsonThatIsNoObjectThrows(string $json, string $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('JSON error: the request body must be a JSON object, ' . $type . ' given');
        JsonRequestBody::fromString(json: $json);
    }

    public function testRequiredString(): void
    {
        $body = JsonRequestBody::fromString(json: '{"a":"  x  "}');

        $this->assertSame('x', $body->getRequiredString(keyName: 'a'));
        $this->assertSame('x', $body->getOptionalString(keyName: 'a'));
    }

    public function testMissingRequiredStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing JSON property (string): a');
        JsonRequestBody::fromString(json: '{}')->getRequiredString(keyName: 'a');
    }

    public function testEmptyRequiredStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing JSON property (string): a');
        JsonRequestBody::fromString(json: '{"a":""}')->getRequiredString(keyName: 'a');
    }

    public function testOptionalEmptyStringIsKept(): void
    {
        $this->assertSame('', JsonRequestBody::fromString(json: '{"a":" "}')->getOptionalString(keyName: 'a'));
    }

    public function testWrongTypeOfStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid JSON property (string): a');
        JsonRequestBody::fromString(json: '{"a":1}')->getOptionalString(keyName: 'a');
    }

    public function testExplicitNullIsMissing(): void
    {
        $body = JsonRequestBody::fromString(json: '{"a":null}');

        $this->assertNull($body->getOptionalString(keyName: 'a'));
        $this->assertNull($body->getOptionalInteger(keyName: 'a'));
    }

    public function testInteger(): void
    {
        $body = JsonRequestBody::fromString(json: '{"a":5,"b":"5","c":1.5}');

        $this->assertSame(5, $body->getRequiredInteger(keyName: 'a'));
        $this->assertSame(5, $body->getOptionalInteger(keyName: 'a'));
        $this->assertNull($body->getOptionalInteger(keyName: 'missing'));
    }

    public function testStringIsNotAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid JSON property (integer): b');
        JsonRequestBody::fromString(json: '{"b":"5"}')->getOptionalInteger(keyName: 'b');
    }

    public function testFloatIsNotAnInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid JSON property (integer): c');
        JsonRequestBody::fromString(json: '{"c":1.5}')->getOptionalInteger(keyName: 'c');
    }

    public function testMissingRequiredIntegerThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing JSON property (integer): a');
        JsonRequestBody::fromString(json: '{}')->getRequiredInteger(keyName: 'a');
    }

    public function testFloatAcceptsIntegers(): void
    {
        $body = JsonRequestBody::fromString(json: '{"a":1.5,"b":2}');

        $this->assertSame(1.5, $body->getRequiredFloat(keyName: 'a'));
        $this->assertSame(2.0, $body->getRequiredFloat(keyName: 'b'));
        $this->assertNull($body->getOptionalFloat(keyName: 'missing'));
    }

    public function testWrongTypeOfFloatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid JSON property (float): a');
        JsonRequestBody::fromString(json: '{"a":"1.5"}')->getOptionalFloat(keyName: 'a');
    }

    public function testMissingRequiredFloatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing JSON property (float): a');
        JsonRequestBody::fromString(json: '{}')->getRequiredFloat(keyName: 'a');
    }

    public function testArray(): void
    {
        $body = JsonRequestBody::fromString(json: '{"a":[1,2]}');

        $this->assertSame([1, 2], $body->getRequiredArray(keyName: 'a'));
        $this->assertSame([1, 2], $body->getOptionalArray(keyName: 'a'));
        $this->assertNull($body->getOptionalArray(keyName: 'missing'));
    }

    public function testEmptyRequiredArrayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Missing JSON property (array): a');
        JsonRequestBody::fromString(json: '{"a":[]}')->getRequiredArray(keyName: 'a');
    }

    public function testWrongTypeOfArrayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Invalid JSON property (array): a');
        JsonRequestBody::fromString(json: '{"a":"x"}')->getOptionalArray(keyName: 'a');
    }
}
