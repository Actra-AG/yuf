<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormField;
use actra\yuf\form\listener\FormFieldListener;
use Override;

/**
 * Records which listener hooks ran, in order.
 */
final class RecordingFieldListener extends FormFieldListener
{
    /** @var list<string> */
    public array $calls = [];

    #[Override]
    public function onEmptyValueBeforeValidation(Form $form, FormField $formField): void
    {
        $this->calls[] = 'emptyBefore';
    }

    #[Override]
    public function onEmptyValueAfterValidation(Form $form, FormField $formField): void
    {
        $this->calls[] = 'emptyAfter';
    }

    #[Override]
    public function onNotEmptyValueBeforeValidation(Form $form, FormField $formField): void
    {
        $this->calls[] = 'notEmptyBefore';
    }

    #[Override]
    public function onNotEmptyValueAfterValidation(Form $form, FormField $formField): void
    {
        $this->calls[] = 'notEmptyAfter';
    }

    #[Override]
    public function onValidationError(Form $form, FormField $formField): void
    {
        $this->calls[] = 'error';
    }

    #[Override]
    public function onValidationSuccess(Form $form, FormField $formField): void
    {
        $this->calls[] = 'success';
    }
}
