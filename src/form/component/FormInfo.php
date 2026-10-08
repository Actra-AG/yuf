<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component;

use actra\yuf\form\FormComponent;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\FormInfoRenderer;
use actra\yuf\html\HtmlText;
use Override;

final class FormInfo extends FormComponent
{
    /**
     * @param list<string> $dlClasses
     * @param list<string> $dtClasses
     * @param list<string> $ddClasses
     */
    public function __construct(
        public readonly HtmlText $title,
        public readonly HtmlText $content,
        public readonly array $dlClasses = [],
        public readonly array $dtClasses = [],
        public readonly array $ddClasses = [],
    ) {
        parent::__construct(name: bin2hex(string: random_bytes(length: 8)));
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new FormInfoRenderer(formInfo: $this);
    }
}
