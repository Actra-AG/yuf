<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\NumericFieldRenderer;
use actra\yuf\html\HtmlText;

/**
 * An integer field for digit codes (e.g. a house number): renders `inputmode="numeric"` and a `pattern` from
 * `minLength` and `maxLength`. It is an integer field, so leading zeros are not kept (`'007'` becomes `7`).
 */
final class NumericField extends IntegerField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?int $initialValue = null,
        ?HtmlText $individualInvalidError = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        public readonly int $minLength = 0,
        ?int $maxLength = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            initialValue: $initialValue,
            individualInvalidError: $individualInvalidError,
            requiredError: $requiredError,
            placeholder: $placeholder,
            maxLength: $maxLength,
        );
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new NumericFieldRenderer(numericField: $this);
    }
}
