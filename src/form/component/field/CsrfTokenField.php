<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormInput;
use actra\yuf\form\InputShapeEnum;
use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\security\CsrfToken;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\security\SessionCsrfTokenSource;
use Override;

/**
 * The hidden field with the CSRF token of the user. It renders the token of the `CsrfTokenSource` (read when the
 * field is rendered, so the session is not touched before) and checks the posted token against it. It has neither a
 * getter nor a setter: the posted token is of no use to a project and the token cannot be overwritten.
 *
 * A token that is missing in the posted data is taken from the query string (the fallback for forms that are sent
 * with a token in the URL).
 */
final class CsrfTokenField extends InputField
{
    private ?string $queryToken = null;
    private bool $postedTokenIsValid = false;

    public function __construct(private readonly CsrfTokenSource $tokenSource = new SessionCsrfTokenSource())
    {
        parent::__construct(
            inputType: InputTypeEnum::HIDDEN,
            name: CsrfToken::getFieldName(),
            label: HtmlText::encoded(textContent: ''),
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
    protected function readAdditionalInput(FormInput $input): void
    {
        $this->queryToken = $input->getShape(name: $this->name) === InputShapeEnum::MISSING
            ? $input->getQueryText(key: $this->name)
            : null;
    }

    #[Override]
    protected function accept(string $text): void
    {
        $token = $this->queryToken ?? $text;
        parent::accept(text: $token);
        $this->postedTokenIsValid = $this->tokenSource->isValid(token: $token);
    }

    /**
     * Adds an error if the posted token is not the token of the user. Rejected input (an array) already has its
     * error.
     */
    #[Override]
    public function validateCurrentValue(): bool
    {
        if (!$this->postedTokenIsValid && !$this->hasErrors(withChildElements: false)) {
            $this->addError(errorMessage: HtmlText::unencoded(textContent: $this->messages->invalidCsrfToken));
        }

        return parent::validateCurrentValue();
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
