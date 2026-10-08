<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use SensitiveParameter;

/**
 * A new secret token: `$secret` goes to the user once, `$hash` is what the application stores.
 */
final readonly class GeneratedSecretToken
{
    public function __construct(#[SensitiveParameter] public string $secret, public SecretTokenHash $hash) {}
}
