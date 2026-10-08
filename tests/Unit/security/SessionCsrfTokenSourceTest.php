<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The CSRF token of the session (`yuf.csrf.token`): generation, validation and the layout of the storage.
 */
final class SessionCsrfTokenSourceTest extends TestCase
{
    private ArraySessionStorage $storage;
    private Session $session;
    private SessionCsrfTokenSource $source;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->session = new Session(storage: $this->storage);
        $this->source = new SessionCsrfTokenSource(session: $this->session);
    }

    private function seedToken(string $token): void
    {
        $this->storage->set(key: 'yuf', value: ['csrf' => ['token' => $token]]);
    }

    public function testStorageLayoutIsTheCsrfSectionWithTheTokenAsString(): void
    {
        $token = $this->source->getToken();

        $this->assertSame(['yuf' => ['csrf' => ['token' => $token]]], $this->storage->all());
    }

    public function testTokenIsBase64OfThirtyTwoRandomBytes(): void
    {
        $token = $this->source->getToken();

        $this->assertSame(44, strlen(string: $token));
        $decoded = base64_decode(string: $token, strict: true);
        $this->assertIsString($decoded);
        $this->assertSame(32, strlen(string: $decoded));
        $this->assertSame($token, base64_encode(string: $decoded));
    }

    public function testTokenIsCreatedOnFirstUseAndStaysTheSame(): void
    {
        $this->assertSame([], $this->storage->all());

        $token = $this->source->getToken();

        $this->assertSame($token, $this->source->getToken());
        $this->assertSame($token, new SessionCsrfTokenSource(session: $this->session)->getToken());
    }

    public function testTokenOfTheSessionIsReturned(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertSame('session-token', $this->source->getToken());
    }

    public function testTokensDifferBetweenSessions(): void
    {
        $other = new SessionCsrfTokenSource(session: new Session(storage: new ArraySessionStorage()));

        $this->assertNotSame($this->source->getToken(), $other->getToken());
    }

    public function testTokenOfTheSessionIsValid(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertTrue($this->source->isValid(token: 'session-token'));
    }

    public function testAnotherTokenIsInvalid(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertFalse($this->source->isValid(token: 'other'));
        $this->assertFalse($this->source->isValid(token: 'session-token '));
        $this->assertFalse($this->source->isValid(token: 'SESSION-TOKEN'));
        $this->assertFalse($this->source->isValid(token: ''));
    }

    /**
     * Fail closed: without a token in the session no posted token is valid, and checking does not create one.
     */
    public function testValidationWithoutTokenInTheSessionIsInvalidAndWritesNothing(): void
    {
        $this->assertFalse($this->source->isValid(token: 'anything'));
        $this->assertFalse($this->source->isValid(token: ''));

        $this->assertSame([], $this->storage->all());
    }

    public function testEmptyStoredTokenCountsAsMissing(): void
    {
        $this->seedToken(token: '');

        $this->assertFalse($this->source->isValid(token: ''));
        $this->assertSame(44, strlen(string: $this->source->getToken()));
    }

    public function testStoredTokenThatIsNoStringCountsAsMissing(): void
    {
        $this->storage->set(key: 'yuf', value: ['csrf' => ['token' => 12345]]);

        $this->assertFalse($this->source->isValid(token: '12345'));
        $this->assertSame(44, strlen(string: $this->source->getToken()));
    }

    public function testTokenIsNotRenewedByAValidation(): void
    {
        $this->seedToken(token: 'session-token');

        $this->source->isValid(token: 'session-token');
        $this->source->isValid(token: 'wrong');

        $this->assertSame('session-token', $this->source->getToken());
    }

    public function testRenewReplacesTheToken(): void
    {
        $this->seedToken(token: 'session-token');

        $token = $this->source->renew();

        $this->assertNotSame('session-token', $token);
        $this->assertSame($token, $this->source->getToken());
        $this->assertTrue($this->source->isValid(token: $token));
        $this->assertFalse($this->source->isValid(token: 'session-token'));
    }

    public function testTokenIsNewAfterTheUserDataWasCleared(): void
    {
        $token = $this->source->getToken();

        $this->session->clearUserData();

        $this->assertSame([], $this->storage->all());
        $this->assertNotSame($token, $this->source->getToken());
    }
}
