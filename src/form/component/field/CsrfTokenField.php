<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\security\CsrfTokenSource;
use Override;

/**
 * The hidden field with the CSRF token of the user. It renders the token of the `CsrfTokenSource` (read when the
 * field is rendered, so the session is not touched before) and checks the posted token against it. It has neither a
 * getter nor a setter: the posted token is of no use to a project and the token cannot be overwritten. The token is
 * only read from the posted data, never from the query string (tokens do not belong into URLs).
 */
final class CsrfTokenField extends InputField
{
    private bool $postedTokenIsValid = false;

    public function __construct(private readonly CsrfTokenSource $tokenSource)
    {
        parent::__construct(
            inputType: InputTypeEnum::HIDDEN,
            name: CsrfTokenSource::FIELD_NAME,
            label: HtmlText::fromHtml(html: ''),
            placeholder: null,
            autoComplete: null,
        );
        $this->setRenderer(renderer: new HiddenFieldRenderer(hiddenField: $this));
    }

    /**
     * The token is sent back exactly as rendered, so it is not trimmed.
     */
    #[Override]
    protected function normalize(string $input): string
    {
        return $this->removeZeroWidthSpaces(input: $input);
    }

    #[Override]
    protected function accept(string $text): void
    {
        parent::accept(text: $text);
        $this->postedTokenIsValid = $this->tokenSource->isValid(token: $text);
    }

    /**
     * Adds an error if the posted token is not the token of the user. Rejected input (an array) already has its
     * error.
     */
    #[Override]
    public function validateCurrentValue(): bool
    {
        if (!$this->postedTokenIsValid && !$this->hasErrors(withChildElements: false)) {
            $this->addError(errorMessage: HtmlText::fromText(text: $this->messages->invalidCsrfToken));
        }

        return parent::validateCurrentValue();
    }

    /**
     * The token is never a value of the user: the field has no initial value and is never changed by the user, so a
     * change check of the form (`Form::hasChanges()`) is not triggered by the posted token.
     */
    #[Override]
    public function valueHasChanged(): bool
    {
        return false;
    }

    /**
     * Always the token of the user, never the posted one.
     */
    #[Override]
    public function renderValue(): string
    {
        return HtmlEncoder::encode(value: $this->tokenSource->getToken());
    }
}
