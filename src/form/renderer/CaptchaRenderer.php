<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\CaptchaField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use Override;

/**
 * The widget of `CaptchaField`.
 *
 * @internal
 */
final class CaptchaRenderer extends FormRenderer
{
    public function __construct(private readonly CaptchaField $captchaField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        return $this->captchaField->formCaptcha->createWidgetTag();
    }
}
