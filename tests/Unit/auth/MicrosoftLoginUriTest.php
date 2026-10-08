<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\MicrosoftLoginUri;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MicrosoftLoginUriTest extends TestCase
{
    public function testUriHasTheParametersOfTheImplicitFlow(): void
    {
        $uri = MicrosoftLoginUri::create(
            tenantId: 'tenant-1',
            clientId: 'client-1',
            redirectUri: 'https://example.com/login?x=1&y=2',
            ssoNonce: 'nonce-1',
        );

        $this->assertSame(
            'https://login.microsoftonline.com/tenant-1/oauth2/v2.0/authorize?client_id=client-1&response_type=id_token'
            . '&redirect_uri=https%3A%2F%2Fexample.com%2Flogin%3Fx%3D1%26y%3D2&response_mode=form_post&scope=openid'
            . '&nonce=nonce-1',
            $uri,
        );
    }

    public function testValuesCannotAddParameters(): void
    {
        $uri = MicrosoftLoginUri::create(
            tenantId: 'tenant-1',
            clientId: 'client&scope=profile',
            redirectUri: 'https://example.com/',
            ssoNonce: 'a b',
        );

        $this->assertStringContainsString('client_id=client%26scope%3Dprofile&', $uri);
        $this->assertStringContainsString('nonce=a%20b', $uri);
    }

    public function testTenantIdThatIsNoHostNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MicrosoftLoginUri::create(tenantId: 'evil.example.com/', clientId: 'c', redirectUri: 'r', ssoNonce: 'n');
    }
}
