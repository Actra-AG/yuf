<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\listener;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormField;

abstract class FormFieldListener
{
    public function onEmptyValueBeforeValidation(Form $form, FormField $formField): void
    {
    }

    public function onEmptyValueAfterValidation(Form $form, FormField $formField): void
    {
    }

    public function onNotEmptyValueBeforeValidation(Form $form, FormField $formField): void
    {
    }

    public function onNotEmptyValueAfterValidation(Form $form, FormField $formField): void
    {
    }

    public function onValidationError(Form $form, FormField $formField): void
    {
    }

    public function onValidationSuccess(Form $form, FormField $formField): void
    {
    }
}