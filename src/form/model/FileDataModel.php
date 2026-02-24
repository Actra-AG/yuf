<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\model;

class FileDataModel
{
    public function __construct(
        private(set) readonly string $name,
        public string $tmp_name,
        private(set) readonly string $type,
        private(set) readonly int $error,
        private(set) readonly int $size
    ) {
    }
}