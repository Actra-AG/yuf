<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\OAuthTokenProvider;
use Override;

/**
 * Returns a fixed access token and counts how often it was asked for it.
 */
final class FixedOAuthTokenProvider implements OAuthTokenProvider
{
    public private(set) int $requests = 0;

    public function __construct(
        private readonly string $accessToken,
    ) {}

    #[Override]
    public function getAccessToken(): string
    {
        $this->requests++;

        return $this->accessToken;
    }
}
