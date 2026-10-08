<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\attachment\MailerAttachmentCollection;
use actra\yuf\mailer\attachment\MailerFileAttachment;
use actra\yuf\mailer\attachment\MailerStringAttachment;
use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerException;
use PHPUnit\Framework\TestCase;

final class MailerAttachmentTest extends TestCase
{
    public function testStringAttachmentTakesTheTypeFromTheFileName(): void
    {
        $attachment = new MailerStringAttachment(contentString: 'x', fileName: ' report.pdf ', type: '');

        $this->assertSame('report.pdf', $attachment->fileName);
        $this->assertSame('application/pdf', $attachment->type);
        $this->assertSame(MailerEncodingEnum::BASE64, $attachment->encoding);
        $this->assertFalse($attachment->dispositionInline);
    }

    public function testStringAttachmentKeepsAnExplicitType(): void
    {
        $attachment = new MailerStringAttachment(contentString: 'x', fileName: 'a.bin', type: ' text/csv ');

        $this->assertSame('text/csv', $attachment->type);
    }

    public function testStringAttachmentRequiresContentAndName(): void
    {
        $this->expectException(MailerException::class);

        new MailerStringAttachment(contentString: '', fileName: 'a.txt', type: '');
    }

    public function testStringAttachmentRequiresAName(): void
    {
        $this->expectException(MailerException::class);

        new MailerStringAttachment(contentString: 'x', fileName: ' ', type: '');
    }

    public function testFileAttachmentDefaults(): void
    {
        $path = __DIR__ . '/../../Fixture/mailer/hello.txt';

        $attachment = new MailerFileAttachment(path: ' ' . $path . ' ');

        $this->assertSame($path, $attachment->path);
        $this->assertSame('hello.txt', $attachment->fileName);
        $this->assertSame('text/plain', $attachment->type);
        $this->assertSame(MailerEncodingEnum::BASE64, $attachment->encoding);
        $this->assertFalse($attachment->dispositionInline);
    }

    public function testFileAttachmentRejectsAnEmptyPath(): void
    {
        $this->expectException(MailerException::class);

        new MailerFileAttachment(path: ' ');
    }

    public function testFileAttachmentRejectsAMissingFile(): void
    {
        $this->expectException(MailerException::class);

        new MailerFileAttachment(path: __DIR__ . '/missing.txt');
    }

    public function testFileAttachmentRejectsAStreamWrapper(): void
    {
        $this->expectException(MailerException::class);

        new MailerFileAttachment(path: 'file://' . __DIR__ . '/../../Fixture/mailer/hello.txt');
    }

    public function testStringAttachmentIsNotTrimmed(): void
    {
        $attachment = new MailerStringAttachment(contentString: " x\n", fileName: 'a.txt', type: '');

        $this->assertSame(" x\n", $attachment->getContent());
        $this->assertSame(" x\n", $attachment->contentString);
    }

    public function testStringAttachmentOnlyKeepsTheLastPartOfTheName(): void
    {
        $this->assertSame(
            'passwd',
            new MailerStringAttachment(contentString: 'x', fileName: '../../etc/passwd', type: '')->fileName,
        );
        $this->assertSame(
            'c.txt',
            new MailerStringAttachment(contentString: 'x', fileName: 'a\\b\\c.txt', type: '')->fileName,
        );
        $this->assertSame(
            'ab.txt',
            new MailerStringAttachment(contentString: 'x', fileName: "a\r\nb.txt\x00", type: '')->fileName,
        );
    }

    public function testStringAttachmentWithoutANameLeftIsRejected(): void
    {
        $this->expectException(MailerException::class);

        new MailerStringAttachment(contentString: 'x', fileName: '/', type: '');
    }

    public function testStringAttachmentRejectsAnInvalidType(): void
    {
        $this->expectException(MailerException::class);

        new MailerStringAttachment(contentString: 'x', fileName: 'a.txt', type: 'text');
    }

    public function testFileAttachmentRejectsAnInvalidType(): void
    {
        $this->expectException(MailerException::class);

        new MailerFileAttachment(path: __DIR__ . '/../../Fixture/mailer/hello.txt', type: "text/plain\nBcc: x");
    }

    public function testFileAttachmentRejectsADirectory(): void
    {
        $this->expectException(MailerException::class);

        new MailerFileAttachment(path: __DIR__);
    }

    public function testFileAttachmentWithOwnNameOnlyKeepsTheLastPartOfTheName(): void
    {
        $attachment = new MailerFileAttachment(
            path: __DIR__ . '/../../Fixture/mailer/hello.txt',
            fileName: '../secret/Bericht.txt',
        );

        $this->assertSame('Bericht.txt', $attachment->fileName);
    }

    public function testFileAttachmentReadsTheFileWhenItIsSent(): void
    {
        $attachment = new MailerFileAttachment(path: __DIR__ . '/../../Fixture/mailer/hello.txt');

        $this->assertSame("Hello from file\n", $attachment->getContent());
    }

    public function testFileThatDisappearedBeforeSendingIsAnException(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-mailer-'
            . bin2hex(string: random_bytes(length: 8)) . '.txt';
        file_put_contents(filename: $path, data: 'x');
        $attachment = new MailerFileAttachment(path: $path);
        unlink(filename: $path);

        $this->expectException(MailerException::class);

        $attachment->getContent();
    }

    public function testCollectionTellsInlineImagesFromAttachments(): void
    {
        $collection = new MailerAttachmentCollection();
        $this->assertFalse($collection->hasInlineImages());
        $this->assertFalse($collection->hasAttachments());

        $collection->addItem(
            mailerAttachment: new MailerStringAttachment(
                contentString: 'x',
                fileName: 'a.png',
                type: '',
                dispositionInline: true,
            ),
        );
        $this->assertTrue($collection->hasInlineImages());
        $this->assertFalse($collection->hasAttachments());

        $collection->addItem(
            mailerAttachment: new MailerStringAttachment(contentString: 'x', fileName: 'b.txt', type: ''),
        );
        $this->assertTrue($collection->hasAttachments());
        $this->assertCount(2, $collection->list());
    }
}
