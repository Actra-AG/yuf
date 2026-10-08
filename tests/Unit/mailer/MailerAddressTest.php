<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerAddress;
use actra\yuf\mailer\MailerAddressCollection;
use actra\yuf\mailer\MailerAddressKindEnum;
use actra\yuf\mailer\MailerCharsetEnum;
use actra\yuf\mailer\MailerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailerAddressTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validAddressProvider(): iterable
    {
        yield 'simple' => ['a@example.com', 'a@example.com'];
        yield 'upper case is lowered' => ['A@Example.COM', 'a@example.com'];
        yield 'surrounding whitespace is trimmed' => [" \ta@example.com\r\n", 'a@example.com'];
        yield 'plus and dot' => ['a.b+c@example.com', 'a.b+c@example.com'];
        yield 'domain literal' => ['a@[127.0.0.1]', 'a@[127.0.0.1]'];
        yield 'idn domain is punycoded' => ['a@ü.example', 'a@xn--tda.example'];
        yield 'idn domain is lower cased' => ['Anna@Bücher.Example', 'anna@xn--bcher-kva.example'];
        yield 'idn domain of another script' => ['a@日本.jp', 'a@xn--wgv71a.jp'];
        yield 'umlaut in the local part stays' => ['ä@example.com', 'ä@example.com'];
    }

    #[DataProvider('validAddressProvider')]
    public function testValidAddress(string $input, string $expected): void
    {
        $address = MailerAddress::createToAddress(inputEmail: $input, inputName: '');

        $this->assertSame($expected, $address->getPunyEncodedEmail());
        $this->assertSame(MailerAddressKindEnum::KIND_TO, $address->mailerAddressKindEnum);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddressProvider(): iterable
    {
        yield 'no at sign' => ['plain'];
        yield 'empty' => [''];
        yield 'no local part' => ['@b.co'];
        yield 'no domain' => ['a@'];
        yield 'space in the local part' => ['a b@example.com'];
        yield 'quoted local part with space' => ['"a b"@example.com'];
        yield 'domain without dot' => ['a@b'];
        yield 'space in the domain' => ['a@exa mple.com'];
        yield 'domain starts with a hyphen' => ['a@-bad.example'];
        yield 'two dots in the local part' => ['a..b@example.com'];
        yield 'closing angle bracket' => ['x@y.co>'];
        yield 'line break in the local part' => ["to@example.com\nBcc: x@example.com"];
        yield 'carriage return in the local part' => ["from@example.com\rBcc: x@example.com"];
        yield 'invalid idn domain' => ["a@a\u{00AD}b ü.example"];
        yield 'line break in the domain' => ["to@example\r\n.com"];
    }

    #[DataProvider('invalidAddressProvider')]
    public function testInvalidAddressIsRejected(string $input): void
    {
        $this->expectException(MailerException::class);

        MailerAddress::createToAddress(inputEmail: $input, inputName: '');
    }

    public function testAddressWithoutNameIsTheBareAddress(): void
    {
        $address = MailerAddress::createFromAddress(inputEmail: 'a@example.com', inputName: '');

        $this->assertSame(
            'a@example.com',
            $address->getFormattedAddressForMailer(maxLineLength: 998, defaultCharSet: MailerCharsetEnum::UTF8),
        );
    }

    public function testNameIsTrimmedAndLineBreaksAreRemoved(): void
    {
        $address = MailerAddress::createFromAddress(inputEmail: 'a@example.com', inputName: " Anna\r\nMeier \n");

        $this->assertSame('AnnaMeier', $address->getName());
        $this->assertSame(
            'AnnaMeier <a@example.com>',
            $address->getFormattedAddressForMailer(maxLineLength: 998, defaultCharSet: MailerCharsetEnum::UTF8),
        );
    }

    public function testLongNonAsciiNameIsFoldedIntoEncodedWords(): void
    {
        $address = MailerAddress::createToAddress(
            inputEmail: 'a@example.com',
            inputName: 'Dr. Hans-Peter Müller-Lüdenscheid von und zu Hohenzollern',
        );

        $this->assertSame(
            "=?utf-8?Q?Dr=2E_Hans-Peter_M=C3=BCller-L=C3=BCdenscheid_von_?=\r\n"
            . ' =?utf-8?Q?und_zu_Hohenzollern?= <a@example.com>',
            $address->getFormattedAddressForMailer(maxLineLength: 63, defaultCharSet: MailerCharsetEnum::UTF8),
        );
    }

    public function testCollectionListsAddressesByKindInTheOrderOfAdding(): void
    {
        $collection = new MailerAddressCollection();
        $collection->addItem(
            mailerAddress: MailerAddress::createToAddress(inputEmail: 'to1@example.com', inputName: ''),
        );
        $collection->addItem(
            mailerAddress: MailerAddress::createCcAddress(inputEmail: 'cc@example.com', inputName: 'C'),
        );
        $collection->addItem(
            mailerAddress: MailerAddress::createToAddress(inputEmail: 'to2@example.com', inputName: ''),
        );

        $this->assertTrue($collection->has(mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO));
        $this->assertFalse($collection->has(mailerAddressKindEnum: MailerAddressKindEnum::KIND_BCC));
        $this->assertSame(
            'to1@example.com, to2@example.com',
            $collection->listAsCommaSeparatedString(
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO,
                maxLineLength: 998,
                defaultCharSet: MailerCharsetEnum::UTF8,
            ),
        );
        $this->assertSame(
            "Cc: C <cc@example.com>\r\n",
            $collection->getHeaderString(
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_CC,
                maxLineLength: 998,
                defaultCharSet: MailerCharsetEnum::UTF8,
            ),
        );
        $this->assertSame(
            '',
            $collection->getHeaderString(
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_BCC,
                maxLineLength: 998,
                defaultCharSet: MailerCharsetEnum::UTF8,
            ),
        );
    }

    public function testCollectionRejectsTheSameAddressTwiceEvenForAnotherKind(): void
    {
        $collection = new MailerAddressCollection();
        $collection->addItem(
            mailerAddress: MailerAddress::createToAddress(inputEmail: 'a@example.com', inputName: ''),
        );

        $this->expectException(MailerException::class);

        $collection->addItem(
            mailerAddress: MailerAddress::createBccAddress(inputEmail: 'A@example.com', inputName: ''),
        );
    }
}
