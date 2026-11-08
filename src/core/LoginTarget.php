<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\auth\LoginHandOver;
use actra\yuf\common\StringUtils;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * The login page that a request of a view with required access rights without a user is redirected to
 * (`RouteCollection::$loginTarget`): a local path on the same host (the requested URI is the return target,
 * `LoginRedirect::RETURN_PARAMETER`), or an absolute `https` URL of a login page on another host on the same server
 * (the request is stored as login request of `LoginHandOver`, the URL carries only its ID).
 */
final readonly class LoginTarget
{
    /**
     * @param string $uri The local path or the absolute URL of the login page, may have a query
     * @param ?string $host The lowercase host of a remote login page, `null` for a local one
     * @param string $path The path of the login page without the query
     * @param ?LoginHandOver $loginHandOver The hand-over of a remote login page, `null` for a local one
     * @param ?string $handOverPath The local path of the hand-over page of this site, `null` for a local login page
     */
    private function __construct(
        public string $uri,
        public ?string $host,
        public string $path,
        private ?LoginHandOver $loginHandOver = null,
        private ?string $handOverPath = null,
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
     * @param string $url Absolute URL of a login page on another host on the same server
     *                    (`https://login.example.net/login.html`, may have a query)
     * @param LoginHandOver $loginHandOver The hand-over through the directory shared with the login host
     * @param string $handOverPath The local path (without query) of the page of this site that redeems the ticket
     *                             (`LoginHandOver::redeemTicket()`)
     *
     * @throws InvalidArgumentException if the URL is no `https` URL with a host and a local path, or has user info, a
     *                                  port or invalid characters (`LoginRedirect::findHttpsHost()`), or the
     *                                  hand-over path is no local path without query
     */
    public static function remote(string $url, LoginHandOver $loginHandOver, string $handOverPath): LoginTarget
    {
        if (!LoginRedirect::isLocalPath(uri: $handOverPath) || str_contains(haystack: $handOverPath, needle: '?')) {
            throw new InvalidArgumentException(message: 'The hand-over path must be a local path without query.');
        }
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
            loginHandOver: $loginHandOver,
            handOverPath: $handOverPath,
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
     * The URI of the login page: for a local login page with the requested URI as return target (not added if it is
     * no local path); for a remote one with the ID of a new login request (`LoginHandOver::startLogin()`, which also
     * sends the browser cookie).
     *
     * @throws LogicException if the host of the request has a port or is no host name (remote login page)
     * @throws RuntimeException if the directory of the hand-over cannot be written (remote login page)
     */
    public function createLoginUri(HttpRequest $httpRequest): string
    {
        if ($this->loginHandOver === null || $this->handOverPath === null) {
            return LoginRedirect::createLoginUri(loginPath: $this->uri, returnUri: $httpRequest->getUri());
        }
        $requestId = $this->loginHandOver->startLogin(httpRequest: $httpRequest, handOverPath: $this->handOverPath);

        return $this->uri . (str_contains(haystack: $this->uri, needle: '?') ? '&' : '?')
            . LoginHandOver::REQUEST_PARAMETER . '=' . $requestId;
    }
}
