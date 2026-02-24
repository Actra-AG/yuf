<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);
/**
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 */

namespace actra\yuf\phone;

class PhoneFormat
{
    private(set) ?string $pattern;
    private(set) ?string $format;
    private array $leadingDigitsPattern = [];

    public function __construct(array $input)
    {
        $this->pattern = $input['pattern'];
        $this->format = $input['format'];
        foreach ($input['leadingDigitsPatterns'] as $leadingDigitsPattern) {
            $this->leadingDigitsPattern[] = $leadingDigitsPattern;
        }
    }

    public function leadingDigitsPatternSize(): int
    {
        return count($this->leadingDigitsPattern);
    }

    public function getLeadingDigitsPattern(int $index): string
    {
        return $this->leadingDigitsPattern[$index];
    }
}