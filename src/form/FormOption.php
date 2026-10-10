<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\html\HtmlText;

/**
 * One entry of `FormOptions`, with the key as the string that is rendered and posted.
 */
final readonly class FormOption
{
    public function __construct(
        public string $key,
        public HtmlText $htmlText,
    ) {}
}
