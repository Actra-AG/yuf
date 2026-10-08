<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\SecretTokenHash;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecretTokenHashTest extends TestCase
{
    public function testGeneratedSecretIsValidForItsHash(): void
    {
        $token = SecretTokenHash::generate();

        $this->assertTrue($token->hash->isValid(secret: $token->secret));
    }

    public function testGeneratedSecretIsBase64UrlWithoutPadding(): void
    {
        $token = SecretTokenHash::generate();

        $this->assertSame(43, strlen(string: $token->secret));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/D', $token->secret);
    }

    public function testGeneratedSecretsDiffer(): void
    {
        $this->assertNotSame(SecretTokenHash::generate()->secret, SecretTokenHash::generate()->secret);
    }

    public function testGeneratedLengthFollowsTheBytes(): void
    {
        $this->assertSame(22, strlen(string: SecretTokenHash::generate(bytes: 16)->secret));
    }

    public function testTooFewBytesAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SecretTokenHash::generate(bytes: 15);
    }

    public function testHashIsSha256InHex(): void
    {
        $this->assertSame(hash(algo: 'sha256', data: 'abc'), SecretTokenHash::fromSecret(secret: 'abc')->hash);
    }

    public function testWrongSecretIsInvalid(): void
    {
        $token = SecretTokenHash::generate();

        $this->assertFalse($token->hash->isValid(secret: $token->secret . 'x'));
        $this->assertFalse($token->hash->isValid(secret: ''));
        $this->assertFalse($token->hash->isValid(secret: strtoupper(string: $token->secret)));
    }

    public function testStoredHashCanBeRestored(): void
    {
        $stored = SecretTokenHash::fromSecret(secret: 'secret')->hash;

        $this->assertTrue(new SecretTokenHash(hash: $stored)->isValid(secret: 'secret'));
    }

    public function testEmptySecretIsRefusedWithoutNamingIt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('A secret token must not be empty.');

        SecretTokenHash::fromSecret(secret: '');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHashProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => [str_repeat(string: 'a', times: 63)];
        yield 'too long' => [str_repeat(string: 'a', times: 65)];
        yield 'upper case' => [str_repeat(string: 'A', times: 64)];
        yield 'not hex' => [str_repeat(string: 'g', times: 64)];
        yield 'trailing line break' => [str_repeat(string: 'a', times: 64) . "\n"];
    }

    #[DataProvider('invalidHashProvider')]
    public function testInvalidStoredHashIsRefused(string $hash): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SecretTokenHash(hash: $hash);
    }

    public function testComparisonIsDoneOnEqualLengthHashes(): void
    {
        // A secret of any length is hashed first, so hash_equals() always compares two 64 character strings
        $hash = SecretTokenHash::fromSecret(secret: 'secret');

        $this->assertFalse($hash->isValid(secret: str_repeat(string: 'x', times: 10_000)));
    }
}
