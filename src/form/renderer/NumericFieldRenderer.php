<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\NumericField;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

/**
 * The input of `NumericField`: `inputmode="numeric"` and a `pattern` for the number of digits.
 *
 * @internal
 */
final class NumericFieldRenderer extends InputFieldRenderer
{
    public function __construct(private readonly NumericField $numericField)
    {
        parent::__construct(formField: $numericField);
    }

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $inputTag = parent::createHtmlTag();
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'inputmode', text: 'numeric'),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(
                name: 'pattern',
                text: '\d{' . $this->getDigitQuantifier() . '}',
            ),
        );

        return $inputTag;
    }

    private function getDigitQuantifier(): string
    {
        $minLength = $this->numericField->minLength;
        $maxLength = $this->numericField->maxLength;
        if ($minLength === $maxLength) {
            return (string) $this->numericField->minLength;
        }
        if ($maxLength === null) {
            return $minLength . ',';
        }
        return $minLength . ',' . $maxLength;
    }
}
