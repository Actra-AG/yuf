<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\CspNonce;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CspNonceTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $this->startNewRequest();
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION);
        $this->startNewRequest();
    }

    private function startNewRequest(): void
    {
        new ReflectionProperty(class: CspNonce::class, property: 'nonce')->setValue(objectOrValue: null, value: null);
    }

    public function testNonceIsBase64OfSixteenRandomBytes(): void
    {
        $nonce = CspNonce::get();

        $this->assertMatchesRegularExpression('#^[A-Za-z0-9+/]{22}==$#', $nonce);
    }

    public function testNonceStaysTheSameWithinTheRequest(): void
    {
        $this->assertSame(CspNonce::get(), CspNonce::get());
    }

    public function testEveryRequestGetsANewNonce(): void
    {
        $firstNonce = CspNonce::get();

        $this->startNewRequest();

        $this->assertNotSame($firstNonce, CspNonce::get());
    }

    public function testNonceIsNotStoredInTheSession(): void
    {
        $_SESSION = [];

        CspNonce::get();

        $this->assertSame([], $_SESSION);
    }
}
