<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\field;

use actra\yuf\html\HtmlTag;
use actra\yuf\security\CsrfToken;

final class CsrfTokenField extends HiddenField
{
    public function __construct()
    {
        parent::__construct(CsrfToken::getFieldName());
    }

    public function getHtmlTag(): ?HtmlTag
    {
        $this->setValue(CsrfToken::getToken());

        return parent::getHtmlTag();
    }
}