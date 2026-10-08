<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Structure of a message, derived from its alternative text, inline images and attachments.
 *
 * @internal
 */
enum MailerMessageTypeEnum: string
{
    // A single body part (not necessarily plain text)
    case PLAIN = 'plain';
    case INLINE = 'inline';
    case ATTACH = 'attach';
    case INLINE_ATTACH = 'inline_attach';
    case ALT = 'alt';
    case ALT_INLINE = 'alt_inline';
    case ALT_ATTACH = 'alt_attach';
    case ALT_INLINE_ATTACH = 'alt_inline_attach';

    public static function fromParts(bool $hasAlternative, bool $hasInlineImages, bool $hasAttachments): self
    {
        return match (true) {
            $hasAlternative && $hasInlineImages && $hasAttachments => MailerMessageTypeEnum::ALT_INLINE_ATTACH,
            $hasAlternative && $hasInlineImages => MailerMessageTypeEnum::ALT_INLINE,
            $hasAlternative && $hasAttachments => MailerMessageTypeEnum::ALT_ATTACH,
            $hasAlternative => MailerMessageTypeEnum::ALT,
            $hasInlineImages && $hasAttachments => MailerMessageTypeEnum::INLINE_ATTACH,
            $hasInlineImages => MailerMessageTypeEnum::INLINE,
            $hasAttachments => MailerMessageTypeEnum::ATTACH,
            default => MailerMessageTypeEnum::PLAIN,
        };
    }

    public function hasAlternative(): bool
    {
        return match ($this) {
            MailerMessageTypeEnum::ALT,
            MailerMessageTypeEnum::ALT_INLINE,
            MailerMessageTypeEnum::ALT_ATTACH,
            MailerMessageTypeEnum::ALT_INLINE_ATTACH => true,
            default => false,
        };
    }

    /**
     * RFC 2045 section 6.4: multipart messages may only use 7bit, 8bit or binary as transfer encoding.
     */
    public function isMultipart(): bool
    {
        return $this !== MailerMessageTypeEnum::PLAIN;
    }

    /**
     * The content type of the whole message.
     */
    public function multipartContentType(): ?MailerContentTypeEnum
    {
        return match ($this) {
            MailerMessageTypeEnum::PLAIN => null,
            MailerMessageTypeEnum::INLINE => MailerContentTypeEnum::MULTIPART_RELATED,
            MailerMessageTypeEnum::ATTACH,
            MailerMessageTypeEnum::INLINE_ATTACH,
            MailerMessageTypeEnum::ALT_ATTACH,
            MailerMessageTypeEnum::ALT_INLINE_ATTACH => MailerContentTypeEnum::MULTIPART_MIXED,
            MailerMessageTypeEnum::ALT,
            MailerMessageTypeEnum::ALT_INLINE => MailerContentTypeEnum::MULTIPART_ALTERNATIVE,
        };
    }
}
