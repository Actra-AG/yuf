<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use Override;

/**
 * 43 letters and digits from 256 random bits.
 */
final readonly class RandomMimeIdGenerator implements MimeIdGenerator
{
    #[Override]
    public function generate(): string
    {
        // Base64 of a hash, without the characters that are not valid in a boundary or in a message id
        return str_replace(
            search: ['=', '+', '/'],
            replace: '',
            subject: base64_encode(
                string: hash(algo: 'sha256', data: random_bytes(length: 32), binary: true),
            ),
        );
    }
}
