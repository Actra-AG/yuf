<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerContentTypeEnum;
use actra\yuf\mailer\MailerMessageTypeEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailerMessageTypeEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool, bool, MailerMessageTypeEnum, ?MailerContentTypeEnum}>
     */
    public static function partsProvider(): iterable
    {
        yield 'nothing' => [false, false, false, MailerMessageTypeEnum::PLAIN, null];
        yield 'inline images' => [
            false,
            true,
            false,
            MailerMessageTypeEnum::INLINE,
            MailerContentTypeEnum::MULTIPART_RELATED,
        ];
        yield 'attachments' => [
            false,
            false,
            true,
            MailerMessageTypeEnum::ATTACH,
            MailerContentTypeEnum::MULTIPART_MIXED,
        ];
        yield 'inline images and attachments' => [
            false,
            true,
            true,
            MailerMessageTypeEnum::INLINE_ATTACH,
            MailerContentTypeEnum::MULTIPART_MIXED,
        ];
        yield 'alternative' => [
            true,
            false,
            false,
            MailerMessageTypeEnum::ALT,
            MailerContentTypeEnum::MULTIPART_ALTERNATIVE,
        ];
        yield 'alternative and inline images' => [
            true,
            true,
            false,
            MailerMessageTypeEnum::ALT_INLINE,
            MailerContentTypeEnum::MULTIPART_ALTERNATIVE,
        ];
        yield 'alternative and attachments' => [
            true,
            false,
            true,
            MailerMessageTypeEnum::ALT_ATTACH,
            MailerContentTypeEnum::MULTIPART_MIXED,
        ];
        yield 'everything' => [
            true,
            true,
            true,
            MailerMessageTypeEnum::ALT_INLINE_ATTACH,
            MailerContentTypeEnum::MULTIPART_MIXED,
        ];
    }

    #[DataProvider('partsProvider')]
    public function testTypeOfTheParts(
        bool $hasAlternative,
        bool $hasInlineImages,
        bool $hasAttachments,
        MailerMessageTypeEnum $expected,
        ?MailerContentTypeEnum $contentType,
    ): void {
        $type = MailerMessageTypeEnum::fromParts(
            hasAlternative: $hasAlternative,
            hasInlineImages: $hasInlineImages,
            hasAttachments: $hasAttachments,
        );

        $this->assertSame($expected, $type);
        $this->assertSame($hasAlternative, $type->hasAlternative());
        $this->assertSame($contentType, $type->multipartContentType());
        $this->assertSame($contentType !== null, $type->isMultipart());
    }
}
