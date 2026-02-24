<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\rule;

use DateTimeImmutable;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use Throwable;

class ValidDateRule extends FormRule
{
    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }
        $value = $formField->getRawValue();
        if (
            preg_match(
                pattern: '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/',
                subject: $value,
                matches: $matches
            ) === 1
        ) {
            $value = implode(
                separator: '-',
                array: [
                    $matches[3],
                    $matches[2],
                    $matches[1],
                ]
            );
        } elseif (
            preg_match(
                pattern: '/^\d{4}-\d{1,2}-\d{1,2}$/',
                subject: $value
            ) !== 1
        ) {
            return false;
        }
        try {
            $dateTime = new DateTimeImmutable(datetime: $value);
            if (DateTimeImmutable::getLastErrors() !== false) {
                return false;
            }
            $formField->setValue(value: $dateTime->format(format: 'Y-m-d'));
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}