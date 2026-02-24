<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;
use actra\yuf\security\CsrfToken;

class ValidCsrfTokenValue extends FormRule
{
    public function __construct()
    {
        parent::__construct(
            defaultErrorMessage: HtmlText::encoded(
                textContent: 'Das Formular konnte wegen eines technischen Problems (ungültiges CSRF) nicht übermittelt werden. Bitte versuchen Sie es erneut.'
            )
        );
    }

    public function validate(FormField $formField): bool
    {
        $token = $formField->getRawValue();
        if (is_null(value: $token)) {
            $token = array_key_exists(key: CsrfToken::getFieldName(), array: $_GET) ? $_GET[CsrfToken::getFieldName(
            )] : '';
        }

        return CsrfToken::validateToken(token: $token);
    }
}