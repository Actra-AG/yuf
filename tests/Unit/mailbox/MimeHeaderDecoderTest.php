<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\MimeHeaderDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MimeHeaderDecoderTest extends TestCase
{
    #[DataProvider('headerProvider')]
    public function testDecodesEncodedWords(string $value, string $expected): void
    {
        $this->assertSame($expected, MimeHeaderDecoder::decode(value: $value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function headerProvider(): iterable
    {
        yield 'plain text' => ['Plain subject', 'Plain subject'];
        yield 'empty' => ['', ''];
        yield 'Q with underscore' => ['=?UTF-8?Q?Gr=C3=BCsse_aus_Z=C3=BCrich?=', 'Grüsse aus Zürich'];
        yield 'B' => ['=?utf-8?b?R3LDvHNzZQ==?=', 'Grüsse'];
        yield 'ISO-8859-1' => ['=?ISO-8859-1?Q?M=FCller?=', 'Müller'];
        yield 'text around words' => ['Re: =?UTF-8?Q?Gr=C3=BCsse?= und mehr', 'Re: Grüsse und mehr'];
        yield 'whitespace between words removed' => ["=?UTF-8?Q?a?= \t =?UTF-8?Q?b?=", 'ab'];
        yield 'folded words (unfolded)' => ['=?UTF-8?Q?Die_Gr=C3=B6?= =?UTF-8?Q?=C3=9Fe?=', 'Die Größe'];
        yield 'character split over two words' => ['=?UTF-8?Q?Gr=C3?= =?UTF-8?Q?=B6sse?=', 'Grösse'];
        yield 'different charsets' => ['=?ISO-8859-1?Q?=E4?= =?UTF-8?Q?=C3=B6?=', 'äö'];
        yield 'language of RFC 2231' => ['=?UTF-8*de?Q?Gr=C3=BCsse?=', 'Grüsse'];
        yield 'raw UTF-8' => ['Grüsse', 'Grüsse'];
        yield 'raw Latin-1' => ["Gr\xFCsse", 'Grüsse'];
        yield 'invalid base64' => ['=?UTF-8?B?!!!?=', ''];
        yield 'unknown charset' => ['=?x-unknown?Q?Text?=', 'Text'];
    }
}
