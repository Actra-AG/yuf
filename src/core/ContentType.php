<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

final readonly class ContentType
{
    public const string HTML = 'html';
    public const string JSON = 'json';
    public const string XML = 'xml';
    public const string TXT = 'txt';
    public const string CSV = 'csv';
    public const string JS = 'js';
    public const string CSS = 'css';
    public const string JPG = 'jpg';
    public const string GIF = 'gif';
    public const string PNG = 'png';
    public const string MOV = 'mov';

    /**
     * @param string $type The file extension of the content (`html`, `json`, `pdf`, …): not a fixed set, so no enum;
     *                     the constants name the types yuf knows
     */
    public function __construct(
        public string $type,
        public MimeType $mimeType,
        public bool $forceDownloadByDefault,
        public ?string $charset,
        public ?string $languageCode,
    ) {}

    public static function createFromFileExtension(string $extension): ContentType
    {
        $extension = mb_strtolower(string: trim(string: $extension));

        return match ($extension) {
            ContentType::HTML => ContentType::createHtml(),
            ContentType::JSON => ContentType::createJson(),
            ContentType::XML => ContentType::createXml(),
            ContentType::TXT => ContentType::createTxt(),
            ContentType::CSV => ContentType::createCsv(),
            ContentType::JS => ContentType::createJs(),
            default => ContentType::createDefault(type: $extension),
        };
    }

    /**
     * @param ?string $languageCode Sent as `Content-Language`; none without a language
     */
    public static function createHtml(?string $languageCode = null): ContentType
    {
        return new ContentType(
            type: ContentType::HTML,
            mimeType: MimeType::createHtml(),
            forceDownloadByDefault: false,
            charset: 'utf-8',
            languageCode: $languageCode,
        );
    }

    public static function createJson(): ContentType
    {
        return new ContentType(
            type: ContentType::JSON,
            mimeType: MimeType::createJson(),
            forceDownloadByDefault: false,
            charset: 'utf-8',
            languageCode: null,
        );
    }

    public static function createXml(): ContentType
    {
        return new ContentType(
            type: ContentType::XML,
            mimeType: MimeType::createXml(),
            forceDownloadByDefault: false,
            charset: 'utf-8',
            languageCode: null,
        );
    }

    public static function createTxt(): ContentType
    {
        return new ContentType(
            type: ContentType::TXT,
            mimeType: MimeType::createTxt(),
            forceDownloadByDefault: false,
            charset: 'utf-8',
            languageCode: null,
        );
    }

    public static function createCsv(): ContentType
    {
        return new ContentType(
            type: ContentType::CSV,
            mimeType: MimeType::createCsv(),
            forceDownloadByDefault: true,
            charset: 'utf-8',
            languageCode: null,
        );
    }

    public static function createJs(): ContentType
    {
        return new ContentType(
            type: ContentType::JS,
            mimeType: MimeType::createJs(),
            forceDownloadByDefault: false,
            charset: 'utf-8',
            languageCode: null,
        );
    }

    private static function createDefault(string $type): ContentType
    {
        return new ContentType(
            type: $type,
            mimeType: MimeType::createByFileExtension(extension: $type),
            forceDownloadByDefault: !in_array(
                needle: $type,
                haystack: [
                    ContentType::CSS,
                    ContentType::JPG,
                    ContentType::GIF,
                    ContentType::PNG,
                    ContentType::MOV,
                ],
                strict: true,
            ),
            charset: null,
            languageCode: null,
        );
    }

    public function isHtml(): bool
    {
        return ($this->type === ContentType::HTML);
    }

    public function isJson(): bool
    {
        return ($this->type === ContentType::JSON);
    }

    public function isTxt(): bool
    {
        return ($this->type === ContentType::TXT);
    }

    public function isCsv(): bool
    {
        return ($this->type === ContentType::CSV);
    }

    public function getHttpHeaderString(): string
    {
        $contentType = $this->mimeType->value;
        if ($this->charset !== null) {
            $contentType .= '; charset=' . $this->charset;
        }

        return $contentType;
    }
}
