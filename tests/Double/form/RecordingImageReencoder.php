<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\upload\ImageReencoder;
use Override;

/**
 * Re-encodes nothing: records the calls and answers with a fixed size, or `null` to simulate a broken image.
 */
final class RecordingImageReencoder implements ImageReencoder
{
    /** @var list<array{path: string, mimeType: string}> */
    public array $calls = [];

    public function __construct(private readonly ?int $newSize = 42) {}

    #[Override]
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'image/jpeg';
    }

    #[Override]
    public function reencode(string $path, string $mimeType): ?int
    {
        $this->calls[] = ['path' => $path, 'mimeType' => $mimeType];

        return $this->newSize;
    }
}
