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

class NumericFieldRenderer extends InputFieldRenderer
{
    public function __construct(private readonly NumericField $numericField)
    {
        parent::__construct(formField: $numericField);
    }

    public function prepare(): void
    {
        parent::prepare();
        $inputTag = $this->getHtmlTag();
        if ($inputTag === null) {
            throw new LogicException(message: 'The input tag is missing after InputFieldRenderer::prepare().');
        }
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'inputmode',
                value: 'numeric',
                valueIsEncodedForRendering: true,
            ),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'pattern',
                value: '\d{' . $this->getDigitQuantifier() . '}',
                valueIsEncodedForRendering: true,
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
