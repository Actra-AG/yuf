<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

/**
 * Template data object for the selector tests: public property, getters with every prefix, and the cases that the old
 * template engine cannot resolve.
 */
final class SelectorTarget
{
    public string $label = 'public label';
    private string $title = 'getter title';
    private string $secret = 'no getter';

    public function __construct(
        private bool $enabled = true,
        private bool $comments = false,
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function hasComments(): bool
    {
        return $this->comments;
    }

    /**
     * A getter without a property of the same name.
     */
    public function getComputed(): string
    {
        return 'computed';
    }

    public function describe(): string
    {
        return 'described ' . $this->secret;
    }
}
