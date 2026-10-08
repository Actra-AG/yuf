<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerCharsetEnum;
use actra\yuf\mailer\MailerHeaderEncoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailerHeaderEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function headerTextProvider(): iterable
    {
        yield 'plain ascii stays' => ['Hello', 998, 'Hello'];
        yield 'empty' => ['', 998, ''];
        yield 'umlaut' => ['Grüezi', 998, '=?utf-8?Q?Gr=C3=BCezi?='];
        yield 'control character' => ["a\x01b", 998, '=?us-ascii?Q?a=01b?='];
        yield 'equal sign and umlaut' => ['a=b ü', 998, '=?utf-8?Q?a=3Db_=C3=BC?='];
        yield 'question mark' => ['Wie? Ü', 998, '=?utf-8?Q?Wie=3F_=C3=9C?='];
        yield 'long ascii is folded' => [
            'abcdefghijklmnopqrstuvwxyz0123456789abcdefghijkl',
            40,
            "=?us-ascii?Q?abcdefghijklmnopqrstuvwx?=\r\n =?us-ascii?Q?yz0123456789abcdefghijkl?=",
        ];
        yield 'long umlaut text is folded' => [
            'Ein langer Betreff mit Umlauten für Zürich und Genf und Bern',
            63,
            "=?utf-8?Q?Ein_langer_Betreff_mit_Umlauten_f=C3=BCr_Z=C3=BCri?=\r\n =?utf-8?Q?ch_und_Genf_und_Bern?=",
        ];
    }

    #[DataProvider('headerTextProvider')]
    public function testEncodeHeaderText(string $text, int $maxLineLength, string $expected): void
    {
        $this->assertSame(
            $expected,
            MailerHeaderEncoder::encodeText(
                string: $text,
                maxLineLength: $maxLineLength,
                defaultCharSet: MailerCharsetEnum::UTF8,
            ),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function headerPhraseProvider(): iterable
    {
        yield 'single word' => ['Anna', 'Anna'];
        yield 'words' => ['Anna Meier', 'Anna Meier'];
        yield 'apostrophe' => ["O'Neil", "O'Neil"];
        yield 'comma is quoted' => ['Doe, John', '"Doe, John"'];
        yield 'dot is quoted' => ['J. Doe', '"J. Doe"'];
        yield 'at sign is quoted' => ['a@b', '"a@b"'];
        yield 'quote is escaped' => ['a"b', '"a\"b"'];
        yield 'backslash is escaped' => ['a\\b', '"a\\\\b"'];
        yield 'control character is escaped' => ["a\0b", '"a\000b"'];
        yield 'umlaut' => ['Müller', '=?utf-8?Q?M=C3=BCller?='];
        yield 'umlaut and comma' => ['Müller, Anna', '=?utf-8?Q?M=C3=BCller=2C_Anna?='];
    }

    #[DataProvider('headerPhraseProvider')]
    public function testEncodeHeaderPhrase(string $phrase, string $expected): void
    {
        $this->assertSame(
            $expected,
            MailerHeaderEncoder::encodePhrase(
                string: $phrase,
                maxLineLength: 998,
                defaultCharSet: MailerCharsetEnum::UTF8,
            ),
        );
    }

    public function testEncodeHeaderPhraseFoldsLongNames(): void
    {
        $this->assertSame(
            "=?utf-8?Q?Dr=2E_Hans-Peter_M=C3=BCller-L=C3=BCdenscheid_von_?=\r\n =?utf-8?Q?und_zu_Hohenzollern?=",
            MailerHeaderEncoder::encodePhrase(
                string: 'Dr. Hans-Peter Müller-Lüdenscheid von und zu Hohenzollern',
                maxLineLength: 63,
                defaultCharSet: MailerCharsetEnum::UTF8,
            ),
        );
    }

    public function testSecureHeaderRemovesLineBreaksAndTrims(): void
    {
        $this->assertSame('abcd', MailerHeaderEncoder::secure(string: " a\r\nb\rc\nd "));
    }
}
