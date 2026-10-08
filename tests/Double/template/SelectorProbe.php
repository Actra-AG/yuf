<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

/**
 * Template data object for the selector resolver tests: every member kind that the resolver handles or must refuse.
 */
final class SelectorProbe
{
    public string $name = 'property';
    public string $both = 'property';
    private string $hidden = 'hidden';

    public function getBoth(): string
    {
        return 'getter';
    }

    public function getCaption(): string
    {
        return 'get caption';
    }

    public function isCaption(): bool
    {
        return true;
    }

    public function hasItems(): bool
    {
        return true;
    }

    public function summary(): string
    {
        return 'method ' . $this->hidden;
    }

    public function needsArgument(string $argument): string
    {
        return $argument;
    }

    public static function staticMethod(): string
    {
        return 'static';
    }

    protected function protectedMethod(): string
    {
        return 'protected';
    }
}
