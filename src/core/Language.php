<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

readonly class Language
{
    public function __construct(
        public string $code,
        public string $locale
    ) {
    }
}