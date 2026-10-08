<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use RuntimeException;

/**
 * Where the public keys (JWKS, JSON) of an identity provider come from. `MicrosoftKeySetSource` is the production
 * implementation; tests use a source without network.
 */
interface JsonWebKeySetSource
{
    /**
     * @return string The key set as JSON text
     *
     * @throws RuntimeException if the key set cannot be loaded
     */
    public function download(): string;
}
