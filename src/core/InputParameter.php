<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

readonly class InputParameter
{
    public function __construct(
        public string $name,
        public bool $isRequired,
        public string $description = ''
    ) {
    }
}