<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\renderer\CaptchaRenderer;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\form\spam\FormCaptcha;
use actra\yuf\html\HtmlText;
use Override;

/**
 * The CAPTCHA widget of the spam protection (`Form::addSpamProtection()` with a `FormCaptcha`). It renders the widget
 * of the provider, which posts its response under the name of the field; the form verifies the posted response with
 * the `FormCaptcha`. A response is never rendered again: it can be verified only once.
 */
final class CaptchaField extends InputField
{
    public function __construct(public readonly FormCaptcha $formCaptcha)
    {
        parent::__construct(
            inputType: InputTypeEnum::HIDDEN,
            name: $formCaptcha->fieldName,
            label: HtmlText::fromHtml(html: ''),
            placeholder: null,
            autoComplete: null,
        );
        $this->setRenderer(renderer: new CaptchaRenderer(captchaField: $this));
    }

    /**
     * The value is posted by the widget, so it is not trimmed.
     */
    #[Override]
    protected function normalize(string $input): string
    {
        return $input;
    }

    public function getPostedValue(): string
    {
        return $this->getText();
    }

    /**
     * The response is never a value of the user, so it does not count for `Form::hasChanges()`.
     */
    #[Override]
    public function valueHasChanged(): bool
    {
        return false;
    }

    #[Override]
    public function renderValue(): string
    {
        return '';
    }
}
