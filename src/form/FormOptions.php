<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\html\HtmlText;

class FormOptions
{
    /** @var HtmlText[] */
    private(set) array $data = [];

    public function __construct()
    {
    }

    public function addItem(string $key, HtmlText $htmlText): void
    {
        $this->data[$key] = $htmlText;
    }

    public function exists(string $key): bool
    {
        return array_key_exists(key: $key, array: $this->data);
    }
}