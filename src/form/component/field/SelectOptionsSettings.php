<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\html\HtmlText;

/**
 * The presentation settings that `SelectOptionsField` and `MultiSelectOptionsField` share (read by
 * `SelectOptionsRenderer`). The two classes differ in their value type, so they cannot share a base class.
 */
trait SelectOptionsSettings
{
    /** @var list<string> */
    public private(set) array $cssClasses;
    public private(set) bool $renderEmptyValueOption;
    public private(set) ?string $placeholder;
    private ?HtmlText $individualEmptyValueLabel;
    private bool $hasDefaultEmptyValueText;
    /** @var array<string, string> */
    private array $dataAttributesStorage = [];
    /** @var array<string, string> */
    public array $dataAttributes {
        get => $this->dataAttributesStorage;
    }
    /**
     * The text of the empty option: the individual label, else "-- Please select --" of the form messages for a
     * required field, else empty. Resolved when it is read, so the messages of the form are used.
     */
    public HtmlText $emptyValueLabel {
        get => $this->individualEmptyValueLabel ?? HtmlText::unencoded(
            textContent: $this->hasDefaultEmptyValueText ? $this->messages->selectEmptyOption : '',
        );
    }

    /**
     * @param list<string> $cssClasses
     */
    private function initializeSelectOptionsSettings(
        bool $isRequired,
        ?HtmlText $individualEmptyValueLabel,
        array $cssClasses,
        bool $renderAsChosenEnhancedField,
        bool $renderEmptyValueOption,
        ?string $placeholder,
    ): void {
        $this->hasDefaultEmptyValueText = $isRequired;
        $this->individualEmptyValueLabel = $individualEmptyValueLabel;
        if ($renderAsChosenEnhancedField) {
            $cssClasses[] = 'chosen';
        }
        $this->cssClasses = $cssClasses;
        $this->renderEmptyValueOption = $renderEmptyValueOption;
        $this->placeholder = $placeholder;
    }

    public function addDataAttribute(string $name, string $value): void
    {
        if (str_starts_with(
            haystack: $name,
            needle: 'data-',
        )) {
            $name = substr(string: $name, offset: 5);
        }
        $this->dataAttributesStorage[$name] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getDataAttributes(): array
    {
        return $this->dataAttributesStorage;
    }
}
