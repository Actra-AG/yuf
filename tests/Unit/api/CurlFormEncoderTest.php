<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlFormEncoder;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;

final class CurlFormEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<array-key, mixed>, 1: string}>
     */
    public static function encodingProvider(): iterable
    {
        $object = new class {
            public string $title = 'T';
            public bool $active = true;
            public ?string $note = null;
            private string $secret = 'hidden';

            public function secret(): string
            {
                return $this->secret;
            }
        };

        return [
            'empty' => [[], ''],
            'string' => [['a' => 'b'], 'a=b'],
            'space is %20, not +' => [['a' => 'b c'], 'a=b%20c'],
            'reserved characters' => [['a&b' => 'c=d'], 'a%26b=c%3Dd'],
            'unicode' => [['a' => 'ä€'], 'a=%C3%A4%E2%82%AC'],
            'true and false' => [['a' => true, 'b' => false], 'a=1&b=0'],
            'null is empty, not skipped' => [['a' => null], 'a='],
            'int and float' => [['a' => 12, 'b' => 1.5, 'c' => -3], 'a=12&b=1.5&c=-3'],
            'zero and empty string stay' => [['a' => 0, 'b' => '', 'c' => '0'], 'a=0&b=&c=0'],
            'list' => [['a' => ['x', 'y']], 'a%5B0%5D=x&a%5B1%5D=y'],
            'nested' => [['a' => ['b' => ['c' => true]]], 'a%5Bb%5D%5Bc%5D=1'],
            'object uses public properties' => [['o' => $object], 'o%5Btitle%5D=T&o%5Bactive%5D=1&o%5Bnote%5D='],
            'integer keys' => [[5 => 'x'], '5=x'],
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('encodingProvider')]
    public function testEncode(array $data, string $expected): void
    {
        self::assertSame($expected, CurlFormEncoder::encode(postData: $data));
    }

    public function testResourceIsRejected(): void
    {
        $resource = fopen(filename: 'php://memory', mode: 'r');
        self::assertNotFalse($resource);

        try {
            $this->expectException(InvalidArgumentException::class);

            CurlFormEncoder::encode(postData: ['a' => $resource]);
        } finally {
            fclose(stream: $resource);
        }
    }

    public function testObjectIsReadThroughItsPublicPropertiesEvenIfItIsStringable(): void
    {
        $object = new class implements Stringable {
            public int $id = 3;

            #[Override]
            public function __toString(): string
            {
                return 'text';
            }
        };

        self::assertSame('o%5Bid%5D=3', CurlFormEncoder::encode(postData: ['o' => $object]));
    }
}
