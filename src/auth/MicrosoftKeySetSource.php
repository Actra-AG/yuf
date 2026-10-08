<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * The signing keys of one tenant, downloaded from `login.microsoftonline.com` over HTTPS with certificate and host
 * name verification, without following redirects.
 */
final readonly class MicrosoftKeySetSource implements JsonWebKeySetSource
{
    private const string URL = 'https://login.microsoftonline.com/{tenantId}/discovery/keys';
    private const int TIMEOUT_IN_SECONDS = 10;
    private const int MAX_BYTES = 1_048_576;

    /**
     * @throws InvalidArgumentException if the tenant ID is not a GUID or a domain name
     */
    public function __construct(private string $tenantId)
    {
        MicrosoftTenantId::assertValid(tenantId: $tenantId);
    }

    #[Override]
    public function download(): string
    {
        $context = stream_context_create(options: [
            'http' => ['timeout' => MicrosoftKeySetSource::TIMEOUT_IN_SECONDS, 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        set_error_handler(callback: static fn(): bool => true);
        try {
            $json = file_get_contents(
                filename: str_replace(
                    search: '{tenantId}',
                    replace: $this->tenantId,
                    subject: MicrosoftKeySetSource::URL,
                ),
                use_include_path: false,
                context: $context,
                length: MicrosoftKeySetSource::MAX_BYTES,
            );
        } finally {
            restore_error_handler();
        }
        if ($json === false) {
            throw new RuntimeException(message: 'The Microsoft signing keys could not be downloaded.');
        }

        return $json;
    }
}
