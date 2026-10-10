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
 * The hidden field with the signed render time of the spam protection (`Form::addSpamProtection()`). It renders the
 * posted value again if it is validly signed for the form and not expired (the form is rendered again after field
 * errors, the minimum time does not start again), otherwise the current time; the form checks the posted value with
 * the `SpamCheck`.
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
        $postedValue = $this->getPostedValue();
        $signedRenderTime = $this->spamCheck->isValidRenderTime(formName: $this->formName, signedRenderTime: $postedValue)
            ? $postedValue
            : $this->spamCheck->signRenderTime(formName: $this->formName);

        return HtmlEncoder::encode(value: $signedRenderTime);
    }
}
