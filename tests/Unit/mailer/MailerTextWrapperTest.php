<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerCharsetEnum;
use actra\yuf\mailer\MailerTextWrapper;
use PHPUnit\Framework\TestCase;

final class MailerTextWrapperTest extends TestCase
{
    public function testWrapsAtWordBoundaries(): void
    {
        $this->assertSame(
            "aaa bbb\r\nccc ddd\r\neee fff\r\n",
            MailerTextWrapperTest::wrap(message: 'aaa bbb ccc ddd eee fff', length: 8),
        );
    }

    public function testNormalizesLineBreaksAndKeepsOneTrailingBreak(): void
    {
        $this->assertSame(
            "aaa\r\nbbb\r\nccc\r\nddd\r\n",
            MailerTextWrapperTest::wrap(message: "aaa bbb\nccc\r\nddd\n", length: 5),
        );
    }

    public function testLongWordIsNotSplitOutsideQuotedPrintable(): void
    {
        $this->assertSame(
            "aaaaaaaaaaaaaaa\r\nbb\r\n",
            MailerTextWrapperTest::wrap(message: 'aaaaaaaaaaaaaaa bb', length: 5),
        );
    }

    public function testQuotedPrintableWordIsSplitWithSoftBreaksNotInsideAnEncodedCharacter(): void
    {
        $this->assertSame(
            "=C3=BCaaaaaaaaaaaaaa=\r\naaaaaaaaaaaaaaaaaaaa=\r\naaaaaaaaaaa\r\n",
            MailerTextWrapper::wrap(
                message: '=C3=BCaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                length: 20,
                charSet: MailerCharsetEnum::UTF8,
                qpMode: true,
            ),
        );
    }

    public function testQuotedPrintableSoftBreaksAtSpaces(): void
    {
        $this->assertSame(
            "a b c d e =\r\nf g h i j =\r\nk l m n o =\r\np\r\n",
            MailerTextWrapper::wrap(
                message: 'a b c d e f g h i j k l m n o p',
                length: 10,
                charSet: MailerCharsetEnum::UTF8,
                qpMode: true,
            ),
        );
    }

    private static function wrap(string $message, int $length): string
    {
        return MailerTextWrapper::wrap(
            message: $message,
            length: $length,
            charSet: MailerCharsetEnum::UTF8,
            qpMode: false,
        );
    }
}
