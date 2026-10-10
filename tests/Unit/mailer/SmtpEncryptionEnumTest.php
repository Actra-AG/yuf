<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\SmtpEncryptionEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmtpEncryptionEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{string, SmtpEncryptionEnum}>
     */
    public static function valueProvider(): iterable
    {
        yield 'starttls' => ['starttls', SmtpEncryptionEnum::STARTTLS];
        yield 'implicit_tls' => ['implicit_tls', SmtpEncryptionEnum::IMPLICIT_TLS];
        yield 'none' => ['none', SmtpEncryptionEnum::NONE];
    }

    #[DataProvider('valueProvider')]
    public function testValuesOfTheEnvironmentFile(string $value, SmtpEncryptionEnum $expected): void
    {
        $this->assertSame($expected, SmtpEncryptionEnum::from(value: $value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownValueProvider(): iterable
    {
        yield 'other name' => ['tls'];
        yield 'case name' => ['STARTTLS'];
    }

    #[DataProvider('unknownValueProvider')]
    public function testUnknownValueIsNoEncryption(string $value): void
    {
        $this->assertNull(SmtpEncryptionEnum::tryFrom(value: $value));
    }
}
