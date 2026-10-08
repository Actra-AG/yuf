<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

final readonly class InputParameter
{
    /**
     * @param InputSourceEnum $source Where the value comes from: the query string or the posted form data
     */
    public function __construct(
        public string $name,
        public InputSourceEnum $source,
        public bool $isRequired,
        public string $description = '',
    ) {}
}
