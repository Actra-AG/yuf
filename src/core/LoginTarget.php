<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\common\StringUtils;
use InvalidArgumentException;

/**
 * The login page that a request of a view with required access rights without a user is redirected to
 * (`RouteCollection::$loginTarget`): a local path on the same host, or an absolute `https` URL of a login page on
 * another host of the same operator. The requested page is the return target (`LoginRedirect::RETURN_PARAMETER`):
 * the local URI for a local login page, the absolute URL of the request for a remote one.
 */
final readonly class LoginTarget
{
    /**
     * @param string $uri The local path or the absolute URL of the login page, may have a query
     * @param ?string $host The lowercase host of a remote login page, `null` for a local one
     * @param string $path The path of the login page without the query
     */
    private function __construct(
        public string $uri,
        public ?string $host,
        public string $path,
    ) {}

    /**
     * @param string $path Local path of the login page (`/login.html`, may have a query)
     *
     * @throws InvalidArgumentException if the path is no local path (`LoginRedirect::isLocalPath()`)
     */
    public static function local(string $path): LoginTarget
    {
        if (!LoginRedirect::isLocalPath(uri: $path)) {
            throw new InvalidArgumentException(message: 'The login path must be a local path starting with "/"');
        }

        return new LoginTarget(
            uri: $path,
            host: null,
            path: StringUtils::beforeFirst(string: $path, before: '?'),
        );
    }

    /**
     * @param string $url Absolute URL of a login page on another host (`https://login.example.net/login.html`, may
     *                    have a query)
     *
     * @throws InvalidArgumentException if the URL is no `https` URL with a host and a local path, or has user info, a
     *                                  port or invalid characters (`LoginRedirect::findHttpsHost()`)
     */
    public static function remote(string $url): LoginTarget
    {
        $host = LoginRedirect::findHttpsHost(url: $url);
        if ($host === null) {
            throw new InvalidArgumentException(
                message: 'The login URL must be an https URL with a host and a path, without user info or port',
            );
        }

        $pathAndQuery = substr(string: $url, offset: strlen(string: 'https://' . $host));

        return new LoginTarget(
            uri: $url,
            host: $host,
            path: StringUtils::beforeFirst(string: $pathAndQuery, before: '?'),
        );
    }

    public function isRemote(): bool
    {
        return $this->host !== null;
    }

    /**
     * Whether the request is a request of the login page itself (no redirect loop): the same path, for a remote login
     * page also the same host.
     */
    public function isLoginRequest(HttpRequest $httpRequest): bool
    {
        return $httpRequest->getPath() === $this->path
            && ($this->host === null || strtolower(string: $httpRequest->getHost()) === $this->host);
    }

    /**
     * The URI of the login page with the requested page as return target: the request URI for a local login page,
     * the absolute URL of the request for a remote one. No return target if the request URI is no local path.
     */
    public function createLoginUri(HttpRequest $httpRequest): string
    {
        if ($this->host === null) {
            return LoginRedirect::createLoginUri(loginPath: $this->uri, returnUri: $httpRequest->getUri());
        }
        if (!LoginRedirect::isLocalPath(uri: $httpRequest->getUri())) {
            return $this->uri;
        }

        return $this->uri . (str_contains(haystack: $this->uri, needle: '?') ? '&' : '?')
            . LoginRedirect::RETURN_PARAMETER . '=' . rawurlencode(string: $httpRequest->getUrl());
    }
}
