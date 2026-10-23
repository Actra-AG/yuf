<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\CountryCodeEnum;
use Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CountryCodeEnumTest extends TestCase
{
    public function testEveryCaseIsATwoLetterUpperCaseCodeNamedLikeItsValue(): void
    {
        foreach (CountryCodeEnum::cases() as $case) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/D', $case->value);
            $this->assertSame($case->name, $case->value);
        }
    }

    public function testEveryCaseIsACountryWithAnOwnName(): void
    {
        $codesByName = [];
        foreach (CountryCodeEnum::cases() as $case) {
            // intl returns the bare code for a code that is no region
            $name = Locale::getDisplayRegion(locale: '-' . $case->value, displayLocale: 'en');
            $this->assertIsString($name);
            $this->assertNotSame($case->value, $name, $case->value . ' is no region');
            $this->assertArrayNotHasKey($name, $codesByName, $case->value . ' duplicates another code');
            $codesByName[$name] = $case->value;
        }
    }

    /**
     * @return iterable<string, array{string, ?CountryCodeEnum}>
     */
    public static function codeProvider(): iterable
    {
        yield 'switzerland' => ['CH', CountryCodeEnum::CH];
        yield 'lower case' => ['ch', null];
        yield 'unknown' => ['XX', null];
        yield 'uruguay' => ['UY', CountryCodeEnum::UY];
        yield 'AA is no ISO 3166 code' => ['AA', null];
        yield 'UR is no ISO 3166 code' => ['UR', null];
        yield 'HQ is no country' => ['HQ', null];
        yield 'AN is replaced by CW, SX and BQ' => ['AN', null];
        yield 'YU is replaced by RS and ME' => ['YU', null];
    }

    #[DataProvider('codeProvider')]
    public function testTryFrom(string $code, ?CountryCodeEnum $expected): void
    {
        $this->assertSame($expected, CountryCodeEnum::tryFrom(value: $code));
    }
}
