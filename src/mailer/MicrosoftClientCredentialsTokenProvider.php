<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\api\CurlClient;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\FileCache;
use actra\yuf\common\JsonUtils;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Override;
use SensitiveParameter;
use UnexpectedValueException;

/**
 * Gets access tokens of the Microsoft identity platform with the OAuth 2.0 client credentials flow (an app with a
 * client secret, no user): `POST {authorityUrl}/{tenantId}/oauth2/v2.0/token`.
 *
 * The scope decides what the token is for: `https://graph.microsoft.com/.default` (default) for `GraphMailer`,
 * `https://outlook.office365.com/.default` for `SmtpMailer` with `SmtpAuthMethodEnum::XOAUTH2`. The token is kept in
 * the instance until shortly before it expires (one minute earlier than `expires_in` says), so one instance serves
 * all mails of a request or a long running process with one token request. With a `FileCache` the token also serves
 * the next requests, which then send their mail without a request to the identity platform.
 *
 * Exception messages contain the HTTP status and the `error` / `error_description` of the response, never the client
 * secret or a token.
 */
final class MicrosoftClientCredentialsTokenProvider implements OAuthTokenProvider
{
    public const string GRAPH_SCOPE = 'https://graph.microsoft.com/.default';
    public const string DEFAULT_AUTHORITY_URL = 'https://login.microsoftonline.com';
    private const int EXPIRY_MARGIN_IN_SECONDS = 60;
    private const int CONNECT_TIMEOUT_IN_SECONDS = 3;
    private const int REQUEST_TIMEOUT_IN_SECONDS = 20;
    private const int MAX_RESPONSE_SIZE_IN_BYTES = 1048576;

    private ?string $accessToken = null;
    private ?DateTimeImmutable $validUntil = null;

    /**
     * @param string $tenantId Directory (tenant) ID (GUID) or domain name
     * @param string $authorityUrl Without the tenant; tests use a local server
     * @param ?FileCache $tokenCache Keeps the token for the next requests (a directory of the application that is not
     *                               served: the token grants sending mail)
     *
     * @throws InvalidArgumentException If the tenant ID, the client ID or the secret is empty or the tenant ID has
     *                                  characters that do not belong into a URL path
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        #[SensitiveParameter]
        private readonly string $clientSecret,
        private readonly string $scope = MicrosoftClientCredentialsTokenProvider::GRAPH_SCOPE,
        private readonly string $authorityUrl = MicrosoftClientCredentialsTokenProvider::DEFAULT_AUTHORITY_URL,
        private readonly CurlClient $curlClient = new CurlClient(),
        private readonly Clock $clock = new SystemClock(),
        private readonly ?FileCache $tokenCache = null,
    ) {
        if (preg_match(pattern: '/^[A-Za-z0-9.-]+$/D', subject: $tenantId) !== 1) {
            throw new InvalidArgumentException(
                message: 'The tenant ID must be a directory ID or a domain name (letters, digits, hyphen, dot).',
            );
        }
        if ($clientId === '' || $clientSecret === '' || $scope === '') {
            throw new InvalidArgumentException(message: 'The client ID, the client secret and the scope are required.');
        }
    }

    #[Override]
    public function getAccessToken(): string
    {
        if (
            $this->accessToken !== null
            && $this->validUntil !== null
            && $this->clock->now() < $this->validUntil
        ) {
            return $this->accessToken;
        }
        $cachedToken = $this->tokenCache?->get(key: $this->getCacheKey());
        if ($cachedToken !== null) {
            return $cachedToken;
        }
        $this->requestToken();

        return $this->accessToken ?? throw new MailerException(message: 'No access token was received.');
    }

    private function getCacheKey(): string
    {
        return 'yuf-oauth-token|' . $this->authorityUrl . '|' . $this->tenantId . '|' . $this->clientId . '|'
            . $this->scope;
    }

    private function requestToken(): void
    {
        $this->accessToken = null;
        $this->validUntil = null;
        $request = CurlPostRequest::createWithPostBody(
            requestTargetUrl: rtrim(string: $this->authorityUrl, characters: '/') . '/' . $this->tenantId
                . '/oauth2/v2.0/token',
            postData: [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
                'grant_type' => 'client_credentials',
            ],
        );
        $request->setHttpHeader(key: 'Accept', value: 'application/json');
        $request->setTimeoutInSeconds(
            connectTimeOut: MicrosoftClientCredentialsTokenProvider::CONNECT_TIMEOUT_IN_SECONDS,
            requestTimeOut: MicrosoftClientCredentialsTokenProvider::REQUEST_TIMEOUT_IN_SECONDS,
        );
        $request->setMaxResponseSizeInBytes(
            maxResponseSizeInBytes: MicrosoftClientCredentialsTokenProvider::MAX_RESPONSE_SIZE_IN_BYTES,
        );
        $response = $this->curlClient->send(request: $request);
        if ($response->rawResponseBody === false) {
            throw new MailerException(
                message: 'The request for the OAuth access token failed: ' . $response->errorMessage,
            );
        }
        if ($response->hasErrors()) {
            $details = MailerHttpErrorReader::describeOAuthError(response: $response);
            throw new MailerException(
                message: 'The request for the OAuth access token failed with HTTP status '
                    . $response->curlInfo['http_code'] . ($details === '' ? '.' : ': ' . $details),
            );
        }
        $this->readToken(body: $response->rawResponseBody);
    }

    private function readToken(string $body): void
    {
        try {
            $data = JsonUtils::decodeJsonString(jsonString: $body, returnAssociativeArray: true);
        } catch (JsonException|UnexpectedValueException) {
            throw new MailerException(message: 'The response to the request for the OAuth access token is no JSON.');
        }
        if (!is_array(value: $data)) {
            throw new MailerException(message: 'The response to the request for the OAuth access token is no JSON.');
        }
        $token = array_key_exists(key: 'access_token', array: $data) ? $data['access_token'] : null;
        $tokenType = array_key_exists(key: 'token_type', array: $data) ? $data['token_type'] : null;
        $expiresIn = array_key_exists(key: 'expires_in', array: $data) ? $data['expires_in'] : null;
        if (is_string(value: $expiresIn) && ctype_digit(text: $expiresIn)) {
            $expiresIn = (int) $expiresIn;
        }
        if (
            !is_string(value: $token)
            || $token === ''
            || !is_string(value: $tokenType)
            || strcasecmp(string1: $tokenType, string2: 'Bearer') !== 0
            || !is_int(value: $expiresIn)
            || $expiresIn < 1
        ) {
            throw new MailerException(
                message: 'The response to the request for the OAuth access token has no valid access_token,'
                    . ' token_type Bearer and expires_in.',
            );
        }
        $lifetime = max(0, $expiresIn - MicrosoftClientCredentialsTokenProvider::EXPIRY_MARGIN_IN_SECONDS);
        $this->accessToken = $token;
        $this->validUntil = $this->clock->now()->modify(modifier: '+' . $lifetime . ' seconds');
        if ($lifetime > 0) {
            $this->tokenCache?->set(key: $this->getCacheKey(), value: $token, lifetimeInSeconds: $lifetime);
        }
    }
}
