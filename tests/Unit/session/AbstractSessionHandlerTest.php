<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\session;

use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfToken;
use actra\yuf\session\AbstractSessionHandler;
use Override;
use PHPUnit\Framework\TestCase;

final class AbstractSessionHandlerTest extends TestCase
{
    private const array DATA_WITHOUT_USER_DATA = [
        'sessionCreated' => 1_790_000_000,
        'trustedRemoteAddress' => '192.0.2.1',
        'trustedUserAgent' => 'Browser',
        'lastActivity' => 1_790_000_100,
        'preferredLanguage' => 'de',
        CspNonce::SESSION_INDICATOR => 'nonce',
    ];

    #[Override]
    protected function tearDown(): void
    {
        unset($_SESSION);
    }

    public function testClearUserDataRemovesUserData(): void
    {
        $_SESSION = AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA;
        $_SESSION['auth_userSession'] = ['isLoggedIn' => true, 'authSessionID' => 5];
        $_SESSION[CsrfToken::CSRFTOKENSTORAGE] = 'token';
        $_SESSION['sess_breadcrumb'] = ['home' => ['title' => 'Home', 'link' => 'home']];

        AbstractSessionHandler::clearUserData();

        $this->assertSame(AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA, $_SESSION);
    }

    public function testClearUserDataKeepsSessionHandlerDataLanguageAndCspNonce(): void
    {
        $_SESSION = AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA;

        AbstractSessionHandler::clearUserData();

        $this->assertSame(AbstractSessionHandlerTest::DATA_WITHOUT_USER_DATA, $_SESSION);
    }

    public function testClearUserDataDoesNothingWithoutSession(): void
    {
        unset($_SESSION);

        AbstractSessionHandler::clearUserData();

        $this->assertFalse(AbstractSessionHandler::enabled());
    }
}
