<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\form\spam\SpamCheck;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use Override;

/**
 * The hidden field with the signed render time of the spam protection (`Form::addSpamProtection()`). It always renders
 * the current time, never the posted value; the form checks the posted value with the `SpamCheck`.
 */
final class SpamRenderTimeField extends InputField
{
    public function __construct(
        string $name,
        private readonly string $formName,
        private readonly SpamCheck $spamCheck,
    ) {
        parent::__construct(
            inputType: InputTypeEnum::HIDDEN,
            name: $name,
            label: HtmlText::fromHtml(html: ''),
            placeholder: null,
            autoComplete: null,
        );
        $this->setRenderer(renderer: new HiddenFieldRenderer(hiddenField: $this));
    }

    /**
     * The value is sent back exactly as rendered, so it is not trimmed.
     */
    #[Override]
    protected function normalize(string $input): string
    {
        return $this->removeZeroWidthSpaces(input: $input);
    }

    public function getPostedValue(): string
    {
        return $this->getText();
    }

    /**
     * The render time is never a value of the user, so it does not count for `Form::hasChanges()`.
     */
    #[Override]
    public function valueHasChanged(): bool
    {
        return false;
    }

    #[Override]
    public function renderValue(): string
    {
        return HtmlEncoder::encode(value: $this->spamCheck->signRenderTime(formName: $this->formName));
    }
}
