<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\FormControlRenderer;
use actra\yuf\html\HtmlText;
use Override;

class FormControl extends FormComponent
{
    /** Used without a form; inside a form the messages of the form are used (see resolveMessages()) */
    public FormMessages $messages;
    /** The individual label, the default text is `FormMessages::$cancel` */
    private readonly ?HtmlText $individualCancelLabel;

    public HtmlText $cancelLabel {
        get => $this->individualCancelLabel ?? HtmlText::fromHtml(html: $this->resolveMessages()->cancel);
    }

    public function __construct(
        string $name,
        public private(set) readonly HtmlText $submitLabel,
        public private(set) readonly ?string $cancelLink = null,
        ?HtmlText $cancelLabel = null,
    ) {
        $this->messages = new FormMessages();
        $this->individualCancelLabel = $cancelLabel;

        parent::__construct($name);
    }

    /**
     * The messages of the form this control belongs to, however it was added (`addComponent()` or
     * `addChildComponent()`), else its own messages.
     */
    private function resolveMessages(): FormMessages
    {
        $parent = $this->getParentFormComponent();
        while ($parent !== null) {
            if ($parent instanceof Form) {
                return $parent->messages;
            }
            $parent = $parent->getParentFormComponent();
        }

        return $this->messages;
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new FormControlRenderer($this);
    }
}
