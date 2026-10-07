<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\CspNonce;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CspNonceTest extends TestCase
{
    public function testCreatedNonceIsBase64OfSixteenRandomBytes(): void
    {
        $nonce = CspNonce::create();

        $this->assertMatchesRegularExpression('#^[A-Za-z0-9+/]{22}==$#', $nonce->value);
        $this->assertSame(24, strlen(string: $nonce->value));
        $this->assertSame(16, strlen(string: (string) base64_decode(string: $nonce->value, strict: true)));
    }

    public function testEveryCreatedNonceIsNew(): void
    {
        $this->assertNotSame(CspNonce::create()->value, CspNonce::create()->value);
    }

    public function testConstructorKeepsTheGivenValue(): void
    {
        $this->assertSame('fixed-nonce', new CspNonce(value: 'fixed-nonce')->value);
    }

    public function testEmptyValueThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The CSP nonce must not be empty.');

        new CspNonce(value: '');
    }
}
