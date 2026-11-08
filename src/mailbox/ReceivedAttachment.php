<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * An attachment or inline part (e.g. an image of the HTML) of a `ReceivedMail`. Everything comes from the sender:
 * check the content type and the file name before using them (e.g. in a `Content-Disposition` header of a download).
 */
final readonly class ReceivedAttachment
{
    /**
     * @param string|null $fileName    `filename` of `Content-Disposition`, otherwise `name` of `Content-Type`, decoded
     *                                 (RFC 2231, RFC 2047) to UTF-8, without directories and control characters; `null`
     *                                 if missing
     * @param string      $contentType lower case `type/subtype` as sent, e.g. `application/pdf`
     * @param bool        $isInline    `Content-Disposition: inline`, or no disposition and a `Content-ID` (an image of
     *                                 the HTML)
     * @param string|null $contentId   header `Content-ID` without `<>` (`cid:` URLs of the HTML), `null` if missing
     * @param string      $content     the decoded bytes (base64, quoted-printable)
     */
    public function __construct(
        public ?string $fileName,
        public string $contentType,
        public bool $isInline,
        public ?string $contentId,
        public string $content,
    ) {}

    /**
     * @return int the size of the decoded content in bytes
     */
    public function getSize(): int
    {
        return strlen(string: $this->content);
    }
}
