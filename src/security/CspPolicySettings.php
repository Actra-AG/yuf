<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use actra\yuf\core\HttpRequest;

/**
 * The directives of the Content Security Policy header (reference: https://content-security-policy.com/). A directive
 * with an empty value is left out. The defaults allow nothing inline: scripts and styles need the nonce of the
 * request (`CspNonce`), which is added to `script-src` and `style-src` unless the directive contains `'none'` or
 * `'unsafe-inline'` (a nonce would switch `'unsafe-inline'` off). `{PROTOCOL}` and `{HOST}` in a value are replaced
 * with the protocol and host of the request (a host with other characters than letters, digits, `.`, `:`, `-`, `[` and
 * `]` becomes `invalid.invalid`). The values are trusted configuration of the application, not user input.
 */
final readonly class CspPolicySettings
{
    public const string PROTOCOL_PLACEHOLDER = '{PROTOCOL}';
    public const string HOST_PLACEHOLDER = '{HOST}';

    private const string INVALID_HOST = 'invalid.invalid';
    private const string SELF_WITH_DATA_AND_HOST = "'self' data: " . CspPolicySettings::PROTOCOL_PLACEHOLDER . '://'
        . CspPolicySettings::HOST_PLACEHOLDER;

    public function __construct(
        private string $defaultSrc = CspPolicySettings::SELF_WITH_DATA_AND_HOST,
        private string $styleSrc = "'self'",
        private string $fontSrc = "'self'",
        private string $imgSrc = CspPolicySettings::SELF_WITH_DATA_AND_HOST,
        private string $objectSrc = "'none'",
        private string $mediaSrc = '',
        private string $scriptSrc = "'strict-dynamic'",
        private string $connectSrc = "'none'",
        private string $baseUri = "'self'",
        private string $frameSrc = "'none'",
        private string $frameAncestors = "'none'",
    ) {}

    public function getHttpHeaderDataString(string $nonce, HttpRequest $httpRequest): string
    {
        $directives = [
            'default-src' => $this->defaultSrc,
            'style-src' => CspPolicySettings::addNonce(sources: $this->styleSrc, nonce: $nonce),
            'font-src' => $this->fontSrc,
            'img-src' => $this->imgSrc,
            'object-src' => $this->objectSrc,
            'media-src' => $this->mediaSrc,
            'script-src' => CspPolicySettings::addNonce(sources: $this->scriptSrc, nonce: $nonce),
            'connect-src' => $this->connectSrc,
            'base-uri' => $this->baseUri,
            'frame-src' => $this->frameSrc,
            'frame-ancestors' => $this->frameAncestors,
        ];
        $parts = [];
        foreach ($directives as $name => $sources) {
            if ($sources !== '') {
                $parts[] = $name . ' ' . $sources;
            }
        }
        if ($parts === []) {
            return '';
        }

        return str_replace(
            search: [CspPolicySettings::PROTOCOL_PLACEHOLDER, CspPolicySettings::HOST_PLACEHOLDER],
            replace: [$httpRequest->getProtocol()->value, CspPolicySettings::getSafeHost(httpRequest: $httpRequest)],
            subject: implode(separator: '; ', array: $parts),
        ) . ';';
    }

    /**
     * The host is what the client sent: a value with spaces or semicolons could add directives to the policy.
     */
    private static function getSafeHost(HttpRequest $httpRequest): string
    {
        $host = $httpRequest->getHost();

        return preg_match(pattern: '/^[A-Za-z0-9.:\[\]-]+$/D', subject: $host) === 1
            ? $host
            : CspPolicySettings::INVALID_HOST;
    }

    private static function addNonce(string $sources, string $nonce): string
    {
        if ($sources === '' || $nonce === ''
            || str_contains(haystack: $sources, needle: "'none'")
            || str_contains(haystack: $sources, needle: "'unsafe-inline'")) {
            return $sources;
        }

        return $sources . " 'nonce-" . $nonce . "'";
    }
}
