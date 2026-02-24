<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\rule;

use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneParseException;
use actra\yuf\phone\PhoneRenderer;
use LogicException;

class PhoneNumberRule extends FormRule
{
    public function validate(FormField $formField): bool
    {
        if (!($formField instanceof PhoneNumberField)) {
            throw new LogicException(message: 'The formField must be an instance of PhoneNumberField');
        }
        if ($formField->isValueEmpty()) {
            return true;
        }
        try {
            $phoneNumber = PhoneNumber::createFromString(
                input: $formField->getRawValue(),
                defaultCountryCode: $formField->countryCode
            );
        } catch (PhoneParseException) {
            return false;
        }
        $formField->setValue(value: PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber));

        return true;
    }
}