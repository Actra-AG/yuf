<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\SmtpDataFormatter;
use PHPUnit\Framework\TestCase;

final class SmtpDataFormatterTest extends TestCase
{
    public function testSplitsAtEveryKindOfLineBreak(): void
    {
        $this->assertSame(
            ['X-A: 1', '', 'a', 'b', 'c', ''],
            SmtpDataFormatter::toLines(data: "X-A: 1\r\n\r\na\rb\nc\r\n"),
        );
    }

    public function testEmptyDataIsOneEmptyLine(): void
    {
        $this->assertSame([''], SmtpDataFormatter::toLines(data: ''));
    }

    public function testLinesThatStartWithADotGetAnotherDot(): void
    {
        $this->assertSame(
            ['..', '...a', 'a.b', '..a'],
            SmtpDataFormatter::toLines(data: ".\n..a\na.b\n.a"),
        );
    }

    public function testLongLineIsBrokenAtTheLastSpace(): void
    {
        $line = str_repeat(string: 'a', times: 600) . ' ' . str_repeat(string: 'b', times: 600);

        $lines = SmtpDataFormatter::toLines(data: $line);

        $this->assertSame([str_repeat(string: 'a', times: 600), str_repeat(string: 'b', times: 600)], $lines);
    }

    public function testLongLineWithoutSpaceIsBrokenHard(): void
    {
        $lines = SmtpDataFormatter::toLines(data: str_repeat(string: 'a', times: 1000));

        $this->assertSame([str_repeat(string: 'a', times: 997), 'aaa'], $lines);
    }

    public function testNoLineIsLongerThan998Characters(): void
    {
        $lines = SmtpDataFormatter::toLines(
            data: str_repeat(string: 'word ', times: 1000) . "\n" . str_repeat(string: 'x', times: 5000),
        );

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(998, strlen(string: $line));
        }
    }

    public function testBrokenLinesOfTheHeaderContinueWithATab(): void
    {
        $lines = SmtpDataFormatter::toLines(
            data: 'Subject: ' . str_repeat(string: 'a', times: 1000)
            . "\r\n\r\n" . str_repeat(string: 'b', times: 1000),
        );

        $this->assertSame(
            [
                'Subject:',
                "\t" . str_repeat(string: 'a', times: 996),
                "\taaaa",
                '',
                // The body is no header: no tab
                str_repeat(string: 'b', times: 997),
                'bbb',
            ],
            $lines,
        );
    }

    public function testEveryPartOfABrokenLineThatStartsWithADotIsStuffed(): void
    {
        $lines = SmtpDataFormatter::toLines(data: str_repeat(string: '.', times: 2000));

        $this->assertCount(3, $lines);
        foreach ($lines as $line) {
            $this->assertStringStartsWith('..', $line);
        }
        $this->assertSame(2003, strlen(string: implode(separator: '', array: $lines)));
    }

    public function testNoLineOfTheMessageEndsTheDataByItself(): void
    {
        $lines = SmtpDataFormatter::toLines(data: "Subject: x\r\n\r\nbody\r\n.\r\nmore");

        $this->assertNotContains('.', $lines);
    }
}
