<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\MimeIdGenerator;
use Override;

/**
 * Always returns the same id.
 */
final readonly class FixedMimeIdGenerator implements MimeIdGenerator
{
    public function __construct(private string $id = 'ID') {}

    #[Override]
    public function generate(): string
    {
        return $this->id;
    }
}
