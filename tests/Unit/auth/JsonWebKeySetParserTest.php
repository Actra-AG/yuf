<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\JsonWebKeySetParser;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\tests\Double\auth\TestJwtIssuer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonWebKeySetParserTest extends TestCase
{
    public function testKeysAreReadByKeyId(): void
    {
        $issuer = new TestJwtIssuer(keyId: 'abc');

        $keys = JsonWebKeySetParser::parse(json: $issuer->createKeySetJson());

        $this->assertSame(['abc'], array_keys(array: $keys));
    }

    public function testKeyWithoutCertificateChainIsSkipped(): void
    {
        $issuer = new TestJwtIssuer();
        $json = $issuer->createKeySetJson(
            additionalKeys: [['kty' => 'RSA', 'kid' => 'no-chain', 'n' => 'AQAB', 'e' => 'AQAB']],
        );

        $keys = JsonWebKeySetParser::parse(json: $json);

        $this->assertSame(['key1'], array_keys(array: $keys));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidKeySetProvider(): iterable
    {
        yield 'no json' => ['<html>', 'The key set is not valid JSON.'];
        yield 'scalar' => ['"keys"', 'The key set has no "keys" list.'];
        yield 'no keys' => ['{}', 'The key set has no "keys" list.'];
        yield 'keys is no list' => ['{"keys":"x"}', 'The key set has no "keys" list.'];
        yield 'empty keys' => ['{"keys":[]}', 'The key set contains no usable key.'];
        yield 'no key id' => ['{"keys":[{"x5c":["AAAA"]}]}', 'The key set contains a key without key ID.'];
        yield 'key is no object' => ['{"keys":["x"]}', 'The key set contains a key without key ID.'];
        yield 'only keys without chain' => ['{"keys":[{"kid":"a"}]}', 'The key set contains no usable key.'];
        yield 'empty chain' => ['{"keys":[{"kid":"a","x5c":[]}]}', 'Failed to parse JWK: invalid certificate chain'];
        yield 'chain is no list' => [
            '{"keys":[{"kid":"a","x5c":"x"}]}',
            'Failed to parse JWK: invalid certificate chain',
        ];
        yield 'certificate is no string' => [
            '{"keys":[{"kid":"a","x5c":[1]}]}',
            'Failed to parse JWK: invalid certificate',
        ];
        yield 'certificate is garbage' => [
            '{"keys":[{"kid":"a","x5c":["AAAA"]}]}',
            'Failed to parse JWK: invalid certificate',
        ];
    }

    #[DataProvider('invalidKeySetProvider')]
    public function testInvalidKeySetIsRejected(string $json, string $message): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs($message);

        JsonWebKeySetParser::parse(json: $json);
    }
}
