<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component;

use actra\yuf\form\FormComponent;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\FormControlRenderer;
use actra\yuf\html\HtmlText;

class FormControl extends FormComponent
{
    public function __construct(
        string $name,
        private(set) readonly HtmlText $submitLabel,
        private(set) readonly ?string $cancelLink = null,
        private(set) ?HtmlText $cancelLabel = null
    ) {
        $this->cancelLabel = is_null($cancelLabel) ? HtmlText::encoded('Abbrechen') : $cancelLabel;

        parent::__construct($name);
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new FormControlRenderer($this);
    }
}