<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use InvalidArgumentException;

/**
 * One HTTP request header. The name must be a token and the value must not contain control characters, so a header
 * can never end a line and start another one (header injection).
 */
final readonly class CurlHeader
{
    private const string NAME_PATTERN = '/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/D';
    private const string FORBIDDEN_VALUE_PATTERN = '/[\x00-\x08\x0A-\x1F\x7F]/';

    public function __construct(
        public string $name,
        public string $value,
    ) {
        if (preg_match(pattern: CurlHeader::NAME_PATTERN, subject: $name) !== 1) {
            throw new InvalidArgumentException(
                message: 'The name of an HTTP header must consist of letters, digits and the characters'
                . " !#$%&'*+.^_`|~- only.",
            );
        }
        if (preg_match(pattern: CurlHeader::FORBIDDEN_VALUE_PATTERN, subject: $value) === 1) {
            throw new InvalidArgumentException(
                message: 'The value of the HTTP header ' . $name . ' must not contain control characters or line'
                . ' breaks.',
            );
        }
    }

    public function isNamed(string $otherName): bool
    {
        return strcasecmp(string1: $this->name, string2: $otherName) === 0;
    }

    /**
     * The header line for cURL: cURL removes a header that has no value and sends `Name;` for an empty one.
     */
    public function toLine(): string
    {
        if ($this->value === '') {
            return $this->name . ';';
        }

        return $this->name . ': ' . $this->value;
    }
}
