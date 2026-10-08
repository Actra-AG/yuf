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
        // 42 hexadecimal characters (168 random bits): valid in a boundary and in a message id, always the same length
        return bin2hex(string: random_bytes(length: 21));
    }
}
