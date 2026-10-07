<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\CsrfToken;
use actra\yuf\security\SessionCsrfTokenSource;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The source wraps the static `CsrfToken` (token in `$_SESSION`); the session array is restored after each test.
 */
final class SessionCsrfTokenSourceTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $savedSession;

    #[Override]
    protected function setUp(): void
    {
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SESSION = $this->savedSession;
    }

    public function testTokenIsTheTokenOfTheSession(): void
    {
        $_SESSION[CsrfToken::CSRFTOKENSTORAGE] = 'session-token';

        $this->assertSame('session-token', new SessionCsrfTokenSource()->getToken());
    }

    public function testTokenIsCreatedOnFirstUseAndStaysTheSame(): void
    {
        $source = new SessionCsrfTokenSource();

        $token = $source->getToken();

        $this->assertNotSame('', $token);
        $this->assertSame($token, $source->getToken());
        $this->assertArrayHasKey(CsrfToken::CSRFTOKENSTORAGE, $_SESSION);
        $this->assertSame($token, $_SESSION[CsrfToken::CSRFTOKENSTORAGE]);
    }

    public function testTokenOfTheSessionIsValid(): void
    {
        $_SESSION[CsrfToken::CSRFTOKENSTORAGE] = 'session-token';

        $this->assertTrue(new SessionCsrfTokenSource()->isValid(token: 'session-token'));
    }

    public function testOtherTokenIsInvalid(): void
    {
        $_SESSION[CsrfToken::CSRFTOKENSTORAGE] = 'session-token';

        $this->assertFalse(new SessionCsrfTokenSource()->isValid(token: 'other'));
    }

    public function testEmptyTokenIsInvalid(): void
    {
        $this->assertFalse(new SessionCsrfTokenSource()->isValid(token: ''));
    }
}
