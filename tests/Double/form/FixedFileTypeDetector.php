<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\upload\FileTypeDetector;
use Override;

/**
 * A file type detector that returns a fixed answer: the type of the path if one was set, otherwise the default type.
 * It records the paths it was asked about.
 */
final class FixedFileTypeDetector implements FileTypeDetector
{
    /** @var array<string, ?string> */
    private array $typesByPath = [];
    /** @var list<string> */
    private array $askedPaths = [];

    public function __construct(private readonly ?string $defaultType = 'text/plain') {}

    public function setType(string $path, ?string $type): void
    {
        $this->typesByPath[$path] = $type;
    }

    #[Override]
    public function detectMimeType(string $path): ?string
    {
        $this->askedPaths[] = $path;

        return array_key_exists(key: $path, array: $this->typesByPath) ? $this->typesByPath[$path] : $this->defaultType;
    }

    /**
     * @return list<string>
     */
    public function getAskedPaths(): array
    {
        return $this->askedPaths;
    }
}
