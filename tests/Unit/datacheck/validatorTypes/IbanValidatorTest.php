<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\validatorTypes;

use actra\yuf\datacheck\validatorTypes\IbanValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IbanValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validIbanProvider(): iterable
    {
        yield 'CH with spaces' => ['CH93 0076 2011 6238 5295 7'];
        yield 'CH without spaces' => ['CH9300762011623852957'];
        yield 'CH lower case' => ['ch9300762011623852957'];
        yield 'LI with letters' => ['LI21 0881 0000 2324 013A A'];
        yield 'DE' => ['DE89 3704 0044 0532 0130 00'];
        yield 'AT' => ['AT61 1904 3002 3457 3201'];
        yield 'GB with letters' => ['GB82 WEST 1234 5698 7654 32'];
        yield 'FR with letters' => ['FR14 2004 1010 0505 0001 3M02 606'];
        yield 'IT with letters' => ['IT60 X054 2811 1010 0000 0123 456'];
        yield 'NL with letters' => ['NL91 ABNA 0417 1643 00'];
        yield 'ES' => ['ES91 2100 0418 4502 0005 1332'];
        yield 'BE shortest of the table' => ['BE68 5390 0754 7034'];
        yield 'NO' => ['NO93 8601 1117 947'];
        yield 'spaces anywhere' => [' C H9300 7620116238 52957 '];
    }

    #[DataProvider('validIbanProvider')]
    public function testValidIbanIsAccepted(string $iban): void
    {
        $this->assertTrue(IbanValidator::validate(input: $iban));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIbanProvider(): iterable
    {
        yield 'wrong checksum' => ['CH93 0076 2011 6238 5295 8'];
        yield 'changed digit' => ['CH93 0076 2011 6238 5296 7'];
        yield 'unknown country' => ['US64 SVBK US6S 3300 9588 79'];
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'only the country code' => ['CH'];
        yield 'text' => ['not an iban'];
        yield 'dash' => ['CH93-0076-2011-6238-5295-7'];
        yield 'tab instead of space' => ["CH93\t0076 2011 6238 5295 7"];
        yield 'trailing line break' => ["CH9300762011623852957\n"];
        yield 'non-ASCII letter' => ['CH93 0076 2011 6238 5295 ä'];
        yield 'too long' => [str_repeat(string: 'CH93', times: 20)];
        yield 'huge input' => ['CH' . str_repeat(string: '9', times: 100000)];
    }

    #[DataProvider('invalidIbanProvider')]
    public function testInvalidIbanIsRejected(string $iban): void
    {
        $this->assertFalse(IbanValidator::validate(input: $iban));
    }
}
