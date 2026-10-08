<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\CsrfToken;
use actra\yuf\session\AbstractSessionHandler;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): the static
 * `CsrfToken`. The behaviour tests only use `seedToken()` and `storedToken()`; the key and the value shape of the
 * storage are pinned in `testStorageLayout…()` only (the keys may change in step 2).
 */
final class CsrfTokenTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    private function seedToken(string $token): void
    {
        $_SESSION['csrftoken'] = $token;
    }

    private function storedToken(): ?string
    {
        $token = $_SESSION['csrftoken'] ?? null;

        return is_string(value: $token) ? $token : null;
    }

    public function testStorageLayoutIsTheTopLevelKeyCsrftokenWithTheTokenAsString(): void
    {
        $token = CsrfToken::getToken();

        $this->assertSame(['csrftoken' => $token], $_SESSION);
    }

    public function testTokenIsBase64OfThirtyTwoRandomBytes(): void
    {
        $token = CsrfToken::getToken();

        $this->assertSame(44, strlen(string: $token));
        $decoded = base64_decode(string: $token, strict: true);
        $this->assertIsString($decoded);
        $this->assertSame(32, strlen(string: $decoded));
        $this->assertSame($token, base64_encode(string: $decoded));
    }

    public function testTokenIsCreatedOnFirstUseAndStoredInTheSession(): void
    {
        $this->assertNull($this->storedToken());

        $token = CsrfToken::getToken();

        $this->assertSame($token, $this->storedToken());
    }

    public function testTokenStaysTheSameWithinTheSession(): void
    {
        $token = CsrfToken::getToken();

        $this->assertSame($token, CsrfToken::getToken());
        $this->assertSame($token, CsrfToken::getToken(forceNew: false));
    }

    public function testTokenOfTheSessionIsReturned(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertSame('session-token', CsrfToken::getToken());
    }

    public function testForceNewReplacesTheTokenOfTheSession(): void
    {
        $this->seedToken(token: 'session-token');

        $token = CsrfToken::getToken(forceNew: true);

        $this->assertNotSame('session-token', $token);
        $this->assertSame($token, $this->storedToken());
        $this->assertSame($token, CsrfToken::getToken());
    }

    public function testTokensDifferBetweenSessions(): void
    {
        $first = CsrfToken::getToken();
        $_SESSION = [];

        $this->assertNotSame($first, CsrfToken::getToken());
    }

    public function testTheTokenOfTheSessionIsValid(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertTrue(CsrfToken::validateToken(token: 'session-token'));
    }

    public function testAnotherTokenIsInvalid(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertFalse(CsrfToken::validateToken(token: 'other'));
        $this->assertFalse(CsrfToken::validateToken(token: 'session-token '));
        $this->assertFalse(CsrfToken::validateToken(token: 'SESSION-TOKEN'));
    }

    public function testEmptyTokenIsInvalid(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertFalse(CsrfToken::validateToken(token: ''));
    }

    public function testValidationWithoutTokenInTheSessionIsInvalidAndCreatesAToken(): void
    {
        $this->assertFalse(CsrfToken::validateToken(token: 'anything'));
        $this->assertFalse(CsrfToken::validateToken(token: ''));

        $this->assertNotNull($this->storedToken());
    }

    public function testTokenIsNotRenewedByAValidation(): void
    {
        $this->seedToken(token: 'session-token');

        CsrfToken::validateToken(token: 'session-token');
        CsrfToken::validateToken(token: 'wrong');

        $this->assertSame('session-token', $this->storedToken());
    }

    public function testRenderedHiddenFieldContainsTheTokenOfTheSession(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertSame(
            '<input type="hidden" name="csrftoken" value="session-token">',
            CsrfToken::renderAsHiddenPostField(),
        );
    }

    public function testRenderedHiddenFieldCreatesTheTokenIfMissing(): void
    {
        $html = CsrfToken::renderAsHiddenPostField();

        $this->assertSame(
            '<input type="hidden" name="csrftoken" value="' . $this->storedToken() . '">',
            $html,
        );
    }

    public function testFieldNameIsCsrftoken(): void
    {
        $this->assertSame('csrftoken', CsrfToken::getFieldName());
    }

    public function testTokenIsNewAfterTheUserDataWasCleared(): void
    {
        $token = CsrfToken::getToken();

        AbstractSessionHandler::clearUserData();

        $this->assertNull($this->storedToken());
        $this->assertNotSame($token, CsrfToken::getToken());
    }

    public function testRenderedHiddenFieldIsEmptyWithoutSession(): void
    {
        unset($_SESSION);

        $this->assertSame('', CsrfToken::renderAsHiddenPostField());
        $this->assertFalse(AbstractSessionHandler::enabled());
    }

    /**
     * Without session the token cannot be kept for the next request, so no posted token can ever match.
     */
    public function testWithoutSessionNoTokenIsValid(): void
    {
        unset($_SESSION);

        $this->assertFalse(CsrfToken::validateToken(token: 'anything'));
        $this->assertFalse(CsrfToken::validateToken(token: ''));
    }

    /**
     * Not a feature but today's behaviour: asking for a token without session creates `$_SESSION`, so
     * `AbstractSessionHandler::enabled()` is true afterwards (findings of docs/session/plan.md, step 1).
     */
    public function testGettingATokenWithoutSessionCreatesTheSessionArray(): void
    {
        unset($_SESSION);

        $token = CsrfToken::getToken();

        $this->assertSame(44, strlen(string: $token));
        $this->assertTrue(AbstractSessionHandler::enabled());
    }
}
