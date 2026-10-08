<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\NumericField;
use actra\yuf\html\HtmlTagAttribute;
use LogicException;
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
    public function prepare(): void
    {
        parent::prepare();
        $inputTag = $this->getHtmlTag();
        if ($inputTag === null) {
            throw new LogicException(message: 'The input tag is missing after InputFieldRenderer::prepare().');
        }
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'inputmode', text: 'numeric'),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(
                name: 'pattern',
                text: '\d{' . $this->getDigitQuantifier() . '}',
            ),
        );
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
