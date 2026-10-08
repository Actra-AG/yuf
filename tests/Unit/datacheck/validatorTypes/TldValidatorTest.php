<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\TldValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TldValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function validateProvider(): iterable
    {
        yield 'country code' => ['ch', true];
        yield 'generic' => ['com', true];
        yield 'new generic' => ['academy', true];
        yield 'upper case' => ['COM', true];
        yield 'mixed case' => ['Org', true];
        yield 'first of the list' => ['aaa', true];
        yield 'last of the list' => ['zw', true];
        yield 'punycode of an IDN' => ['xn--p1ai', true];
        yield 'punycode in upper case' => ['XN--P1AI', true];
        yield 'empty' => ['', false];
        yield 'unknown' => ['invalid', false];
        yield 'reserved example' => ['example', false];
        yield 'localhost' => ['localhost', false];
        yield 'one letter' => ['c', false];
        yield 'with dot' => ['.com', false];
        yield 'whitespace' => [' com', false];
        yield 'trailing line break' => ["com\n", false];
        yield 'digits' => ['123', false];
        yield 'unicode IDN is not the encoded form' => ['рф', false];
    }

    #[DataProvider('validateProvider')]
    public function testValidate(string $input, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, TldValidator::validate(input: $input));
    }
}
