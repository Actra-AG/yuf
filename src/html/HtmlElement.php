<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

/**
 * Base of everything that renders to HTML: `HtmlTag`, `HtmlTagAttribute`, `HtmlText` and the form components.
 *
 * Extension point: only for the components of `src/form/`; projects extend `FormComponent` or its subclasses, not
 * this class.
 */
abstract class HtmlElement
{
    /**
     * Protected, so that the subclasses have to offer their own constructor.
     */
    protected function __construct(public readonly string $name) {}

    /**
     * @return string HTML for the output
     */
    abstract public function render(): string;
}
