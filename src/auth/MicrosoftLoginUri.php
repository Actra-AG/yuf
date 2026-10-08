<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use InvalidArgumentException;

/**
 * The address the browser is sent to for the Microsoft login (OpenID Connect implicit flow with form post, see
 * https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-protocols-oidc). Pure function of its arguments,
 * so it stays static.
 *
 * @internal
 */
final readonly class MicrosoftLoginUri
{
    private const string AUTHORIZE_URI = 'https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/authorize';

    /**
     * @throws InvalidArgumentException if the tenant ID is not a GUID or a domain name
     */
    public static function create(string $tenantId, string $clientId, string $redirectUri, string $ssoNonce): string
    {
        MicrosoftTenantId::assertValid(tenantId: $tenantId);

        return str_replace(search: '{tenantId}', replace: $tenantId, subject: MicrosoftLoginUri::AUTHORIZE_URI)
            . '?' . http_build_query(
                data: [
                    'client_id' => $clientId,
                    'response_type' => 'id_token',
                    'redirect_uri' => $redirectUri,
                    'response_mode' => 'form_post',
                    'scope' => 'openid',
                    'nonce' => $ssoNonce,
                ],
                encoding_type: PHP_QUERY_RFC3986,
            );
    }
}
