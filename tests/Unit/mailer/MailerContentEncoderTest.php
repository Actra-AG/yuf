<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerContentEncoder;
use actra\yuf\mailer\MailerEncodingEnum;
use PHPUnit\Framework\TestCase;

final class MailerContentEncoderTest extends TestCase
{
    public function testBase64IsChunkedTo76CharactersAndEndsWithLineBreak(): void
    {
        $this->assertSame(
            chunk_split(
                string: base64_encode(string: str_repeat(string: 'abcdefghij', times: 20)),
                length: 76,
                separator: "\r\n",
            ),
            MailerContentEncoder::encode(
                string: str_repeat(string: 'abcdefghij', times: 20),
                encoding: MailerEncodingEnum::BASE64,
            ),
        );
    }

    public function testEmptyBase64IsALineBreak(): void
    {
        $this->assertSame(
            "\r\n",
            MailerContentEncoder::encode(string: '', encoding: MailerEncodingEnum::BASE64),
        );
    }

    public function testSevenAndEightBitNormalizeLineBreaksAndEndWithOne(): void
    {
        $this->assertSame(
            "a\r\nb\r\nc\r\nd\r\n",
            MailerContentEncoder::encode(string: "a\nb\rc\r\nd", encoding: MailerEncodingEnum::SEVEN_BIT),
        );
        $this->assertSame(
            "a\r\n",
            MailerContentEncoder::encode(string: "a\r\n", encoding: MailerEncodingEnum::EIGHT_BIT),
        );
        $this->assertSame(
            "\r\n",
            MailerContentEncoder::encode(string: '', encoding: MailerEncodingEnum::EIGHT_BIT),
        );
    }

    public function testBinaryIsNotChanged(): void
    {
        $this->assertSame(
            "a\nb",
            MailerContentEncoder::encode(string: "a\nb", encoding: MailerEncodingEnum::BINARY),
        );
    }

    public function testQuotedPrintable(): void
    {
        $this->assertSame(
            "a=3Db\r\nc =C3=BC=20\r\nd=0Ae",
            MailerContentEncoder::encode(
                string: "a=b\r\nc ü \r\nd\ne",
                encoding: MailerEncodingEnum::QUOTED_PRINTABLE,
            ),
        );
    }

    public function testDetects8bitCharacters(): void
    {
        $this->assertFalse(MailerContentEncoder::has8bitChars(text: 'abc'));
        $this->assertFalse(MailerContentEncoder::has8bitChars(text: ''));
        $this->assertTrue(MailerContentEncoder::has8bitChars(text: 'äbc'));
        $this->assertTrue(MailerContentEncoder::has8bitChars(text: "\x80"));
    }
}
