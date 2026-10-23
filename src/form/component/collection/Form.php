<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\collection;

use actra\yuf\form\component\field\CsrfTokenField;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\component\field\SpamRenderTimeField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormCollection;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormContext;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\CompactFieldRenderer;
use actra\yuf\form\renderer\DefaultFormRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\spam\SpamCheckResultEnum;
use actra\yuf\form\spam\SpamProtection;
use actra\yuf\html\HtmlText;
use LogicException;
use Override;

/**
 * The form names must be unique per page: the name is the sent indicator and the prefix of the field names.
 * A POST form is protected against CSRF: it has a CSRF field and `validate()` checks the token before it reads any
 * other field. Without CSRF token source in its `FormContext` (no session) a POST form can only be created with
 * `csrfProtection: false`; a GET form has no CSRF field. Only a form with `acceptUpload: true` takes file fields and
 * uploads; any other form rejects a request with uploads or a multipart body.
 *
 * Extension point: a project form extends `Form`, adds its fields in the constructor and offers methods for the
 * validated values.
 */
class Form extends FormCollection
{
    public readonly string $sentIndicator;
    /** @var list<string> */
    public private(set) array $cssClasses = [];
    /** Seconds until the rate limit of the spam protection allows the next submit; 0 if the submit was not blocked */
    public private(set) int $retryAfterSeconds = 0;
    private bool $renderRequiredAbbr = true;
    private bool $compactFields = false;
    private ?CsrfTokenField $csrfTokenField = null;
    private ?SpamProtection $spamProtection = null;
    private ?TextField $honeypotField = null;
    private ?SpamRenderTimeField $renderTimeField = null;

    public function __construct(
        public readonly FormContext $context,
        string $name,
        public readonly bool $acceptUpload = false,
        public readonly ?HtmlText $globalErrorMessage = null,
        public readonly bool $methodPost = true,
        ?string $individualSentIndicator = null,
        public readonly bool $disableClientValidation = false,
        public readonly FormMessages $messages = new FormMessages(),
        public readonly bool $csrfProtection = true,
    ) {
        $this->sentIndicator = $individualSentIndicator === null ? $name : $individualSentIndicator;
        parent::__construct(name: $name);

        $csrfTokenSource = $context->csrfTokenSource;
        // A GET form must not change state and would put the token into the URL, so it has no CSRF token
        if (!$methodPost || !$csrfProtection) {
            return;
        }
        if ($csrfTokenSource === null) {
            throw new LogicException(
                message: 'Form ' . $name . ' is sent with POST, but the FormContext has no CSRF token source (no'
                . ' session). Create the form with csrfProtection: false if it needs no CSRF protection.',
            );
        }
        $this->addField(formField: $this->csrfTokenField = new CsrfTokenField(tokenSource: $csrfTokenSource));
    }

    /**
     * @throws LogicException if a `FileField` is added to a form without `acceptUpload: true`
     */
    public function addField(FormField $formField): void
    {
        if ($formField instanceof FileField && !$this->acceptUpload) {
            throw new LogicException(
                message: 'Form ' . $this->name . ' needs acceptUpload: true for the file field ' . $formField->name
                . '.',
            );
        }
        if (!$this->renderRequiredAbbr) {
            $formField->renderRequiredAbbr = false;
        }
        $formField->messages = $this->messages;
        $formField->topFormComponent = $this;
        $this->addChildComponent(formComponent: $formField);
    }

    /**
     * Adds the honeypot field (label `FormMessages::$spamHoneypotLabel`, `autocomplete="off"`, to be hidden by the CSS
     * of the project) and the hidden field with the signed render time at this position; `validate()` checks them.
     * A broken rule is an error of the form (`FormMessages::$spamHoneypotFilled`, `$spamRetry`,
     * `$spamRejectedEmailAddress`). With a `FormRateLimit`, `validate()` counts every submit with a valid CSRF token
     * (see there).
     *
     * @throws LogicException if the form already has a spam protection
     */
    public function addSpamProtection(SpamProtection $spamProtection): void
    {
        if ($this->spamProtection !== null) {
            throw new LogicException(message: 'Form ' . $this->name . ' already has a spam protection.');
        }
        $this->spamProtection = $spamProtection;
        $this->addField(
            formField: $this->honeypotField = new TextField(
                name: $spamProtection->honeypotName,
                label: HtmlText::fromText(text: $this->messages->spamHoneypotLabel),
                autoComplete: AutoCompleteEnum::OFF,
            ),
        );
        $this->addField(
            formField: $this->renderTimeField = new SpamRenderTimeField(
                name: $spamProtection->renderTimeName,
                formName: $this->name,
                spamCheck: $spamProtection->spamCheck,
                sessionSource: $this->context->csrfTokenSource,
            ),
        );
    }

    public function getDefaultFormFieldRenderer(FormField $formField): FormRenderer
    {
        if ($this->compactFields) {
            return new CompactFieldRenderer(formField: $formField);
        }

        return new DefinitionListRenderer(formField: $formField);
    }

    /**
     * Renders the fields that have no renderer of their own as label and control (`CompactFieldRenderer`) instead of
     * a definition list, e.g. for a search form. Call it before the form is rendered.
     */
    public function useCompactFieldRenderer(): void
    {
        $this->compactFields = true;
    }

    public function addCssClass(string $className): void
    {
        $this->cssClasses[] = $className;
    }

    public function addComponent(FormComponent $formComponent): void
    {
        if ($formComponent instanceof FormControl) {
            $formComponent->messages = $this->messages;
        }
        $this->addChildComponent(formComponent: $formComponent);
    }

    public function removeField(string $name): void
    {
        if (!$this->hasField(name: $name)) {
            throw new LogicException(
                message: 'Form ' . $this->name . ' has no field ' . $name
                    . ' (it does not exist or is not a FormField).',
            );
        }
        $this->removeChildComponent(childComponentName: $name);
    }

    public function hasField(string $name): bool
    {
        if (!$this->hasChildComponent(childComponentName: $name)) {
            return false;
        }

        $component = $this->getChildComponent(childComponentName: $name);

        return ($component instanceof FormField);
    }

    /**
     * Validates all fields with the request data if the form was sent. A POST form checks the CSRF token first: with
     * a missing or invalid token the form gets the CSRF error and nothing else of the request is read (no fields,
     * listeners, uploads or spam check).
     *
     * Then the rate limit of the spam protection counts the submit, valid or not. If it is reached, the fields only
     * read their input (the typed values are rendered again; no rules, no listeners, no new uploads), the form gets
     * the error `FormMessages::$rateLimited` and `$retryAfterSeconds` is set. A form without `acceptUpload` then
     * rejects a request with uploads or a multipart body (`FormMessages::$invalidInput`, nothing else is read).
     *
     * @param ?FormInput $input The request data, by default the input of the request of the `FormContext` (the post
     *                          data or, for a GET form, the query string)
     */
    public function validate(?FormInput $input = null): bool
    {
        $input ??= $this->createInput();
        if (!$this->isSent(input: $input)) {
            return false;
        }

        if (!$this->isCsrfTokenValid(input: $input)) {
            $this->addError(errorMessage: HtmlText::fromText(text: $this->messages->invalidCsrfToken));

            return false;
        }
        if (!$this->consumeRateLimit()) {
            $this->readValues(input: $input->withoutUploads());
            $this->addError(errorMessage: HtmlText::fromText(text: $this->messages->rateLimited));

            return false;
        }
        if (!$this->acceptUpload && ($input->hasFiles || $input->isMultipart)) {
            $this->addError(errorMessage: HtmlText::fromText(text: $this->messages->invalidInput));

            return false;
        }

        foreach ($this->childComponents as $formComponent) {
            // The CSRF token has been checked above
            if (!$formComponent instanceof FormField || $formComponent instanceof CsrfTokenField) {
                continue;
            }

            $formComponent->validate(input: $input);
        }
        $this->validateSpamProtection();

        if (
            $this->hasErrors(withChildElements: true)
            && !$this->hasErrors(withChildElements: false)
            && $this->globalErrorMessage !== null
        ) {
            $this->addError(errorMessage: $this->globalErrorMessage);
        }

        return !$this->hasErrors(withChildElements: true);
    }

    /**
     * Whether the sent indicator is in the query string of the request.
     *
     * @param ?FormInput $input The request data, by default the input of the request of the `FormContext`
     */
    public function isSent(?FormInput $input = null): bool
    {
        $input ??= $this->createInput();
        return $input->hasQueryKey(key: $this->sentIndicator);
    }

    private function createInput(): FormInput
    {
        return FormInput::fromHttpRequest(httpRequest: $this->context->httpRequest, methodPost: $this->methodPost);
    }

    /**
     * Counts the submit with the rate limit of the spam protection.
     *
     * @return bool Whether the submit may be processed
     */
    private function consumeRateLimit(): bool
    {
        $rateLimit = $this->spamProtection?->rateLimit;
        if ($rateLimit === null) {
            return true;
        }
        $result = $rateLimit->consume(
            formName: $this->name,
            ipAddress: $this->context->httpRequest->getRemoteAddress(),
        );
        $this->retryAfterSeconds = $result->retryAfterSeconds;

        return $result->isAllowed;
    }

    /**
     * Reads the input of the fields without validating them, so the typed values are rendered again.
     */
    private function readValues(FormInput $input): void
    {
        foreach ($this->childComponents as $formComponent) {
            if ($formComponent instanceof FormField && !$formComponent instanceof CsrfTokenField) {
                $formComponent->readValue(input: $input);
            }
        }
    }

    /**
     * Runs only if the fields are valid: fields with errors block the submission anyway, and the global error message
     * stays the error of the form. The form is rendered again with a signed render time, so the
     * next submission is checked. Errors of the spam fields themselves (a manipulated value) do not count, as they are
     * not visible.
     */
    private function validateSpamProtection(): void
    {
        if ($this->spamProtection === null || $this->honeypotField === null || $this->renderTimeField === null) {
            return;
        }
        foreach ($this->getAllFields() as $formField) {
            if (
                $formField !== $this->honeypotField
                && $formField !== $this->renderTimeField
                && $formField->hasErrors(withChildElements: true)
            ) {
                return;
            }
        }
        $emailField = $this->spamProtection->emailField;
        $result = $this->spamProtection->spamCheck->check(
            formName: $this->name,
            honeypot: $this->honeypotField->getValueAsString(),
            signedRenderTime: $this->renderTimeField->getPostedValue(),
            emailAddress: $emailField?->getValueAsString(),
            sessionBinding: $this->context->csrfTokenSource?->getToken(),
        );
        $errorMessage = match ($result) {
            SpamCheckResultEnum::OK => null,
            SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS => $this->messages->spamRejectedEmailAddress,
            SpamCheckResultEnum::HONEYPOT_FILLED => $this->messages->spamHoneypotFilled,
            SpamCheckResultEnum::TOO_FAST, SpamCheckResultEnum::EXPIRED => $this->messages->spamRetry,
        };
        if ($errorMessage !== null) {
            $this->addError(errorMessage: HtmlText::fromText(text: $errorMessage));
        }
    }

    /**
     * Checked through the field the constructor created, so removing the field from the form does not disable the
     * check (the token is then never posted, and the form stays invalid).
     */
    private function isCsrfTokenValid(FormInput $input): bool
    {
        return $this->csrfTokenField === null || $this->csrfTokenField->validate(input: $input);
    }

    public function getField(string $name): FormField
    {
        $childComponent = $this->getChildComponent(childComponentName: $name);
        if (!($childComponent instanceof FormField)) {
            throw new LogicException(
                message: 'The component ' . $name . ' of form ' . $this->name . ' is not a FormField.',
            );
        }

        return $childComponent;
    }

    #[Override]
    public function render(): string
    {
        if (
            $this->hasErrors(withChildElements: true)
            && !$this->hasErrors(withChildElements: false)
            && $this->globalErrorMessage !== null
        ) {
            $this->addError(errorMessage: $this->globalErrorMessage);
        }

        return parent::render();
    }

    /**
     * @return list<FormField>
     */
    public function getAllFields(): array
    {
        $allFields = [];
        foreach ($this->childComponents as $formComponent) {
            if (!$formComponent instanceof FormField) {
                continue;
            }
            $allFields[] = $formComponent;
        }

        return $allFields;
    }

    /**
     * Whether the current value of any field differs from its initial value (`FormField::valueHasChanged()`), also
     * of the child fields of toggle fields. The CSRF field never counts; a typed password and an uploaded file do.
     * Meaningful after `validate()` (the posted values) or after the setters were called.
     */
    public function hasChanges(): bool
    {
        return array_any(
            array: $this->getAllFields(),
            callback: static fn(FormField $formField): bool => $formField->valueHasChanged()
                || Form::hasChangedChildField(formField: $formField),
        );
    }

    private static function hasChangedChildField(FormField $formField): bool
    {
        if (!$formField instanceof ToggleField && !$formField instanceof MultiToggleField) {
            return false;
        }
        foreach ($formField->childrenByMainOption as $children) {
            foreach ($children as $child) {
                if (
                    $child instanceof FormField
                    && ($child->valueHasChanged() || Form::hasChangedChildField(formField: $child))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function dontRenderRequiredAbbr(): void
    {
        $this->renderRequiredAbbr = false;
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new DefaultFormRenderer(form: $this);
    }
}
