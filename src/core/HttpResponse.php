<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\FileHandler;
use actra\yuf\common\UrlHelper;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\session\AbstractSessionHandler;
use LogicException;
use RuntimeException;

/**
 * A response with its headers; created by the `create…()` factories, sent with `sendAndExit()`. Generated content
 * (HTML, JSON, text) is never stored by browsers or proxies (`Cache-Control: private, no-store`): it can contain
 * personal data and CSRF tokens. A file response has validators: a request that already has the current version
 * (`If-None-Match`, `If-Modified-Since`) gets a 304 response without content.
 */
final class HttpResponse
{
    /** One year, independent of how long the response may be cached (max-age=0 would remove HSTS in the browser). */
    private const int HSTS_MAX_AGE = 31536000;

    /** @var array<string, string> */
    private array $headers = [];

    private function __construct(
        public private(set) HttpStatusCodeEnum $httpStatusCode,
        private readonly ?string $contentString = null,
        private readonly ?string $contentFilePath = null,
    ) {}

    private static function createGeneratedContentResponse(
        HttpStatusCodeEnum $httpStatusCode,
        ContentType $contentType,
        string $contentString,
        ?string $eTag,
    ): HttpResponse {
        $httpResponse = new HttpResponse(httpStatusCode: $httpStatusCode, contentString: $contentString);
        if ($eTag === null) {
            $httpResponse->setHeader(key: 'Cache-Control', val: 'private, no-store');
        } else {
            // The browser stores the page and asks with If-None-Match every time (see createNotModifiedResponse())
            $httpResponse->setHeader(key: 'Cache-Control', val: 'private, no-cache');
            $httpResponse->setHeader(key: 'Etag', val: '"' . $eTag . '"');
        }
        $httpResponse->setContentTypeAndSecurityHeaders(contentType: $contentType);

        return $httpResponse;
    }

    /**
     * A response with a status and no headers and no content, so that only the status line is sent (a 404 or 403 for
     * a file, a 405 for an unsupported request method).
     */
    public static function createStatusResponse(HttpStatusCodeEnum $httpStatusCode): HttpResponse
    {
        return new HttpResponse(httpStatusCode: $httpStatusCode);
    }

    private function setContentTypeAndSecurityHeaders(ContentType $contentType): void
    {
        $this->setHeader(
            key: 'Content-Type',
            val: $contentType->getHttpHeaderString(),
        );
        if ($contentType->languageCode !== null) {
            $this->setHeader(
                key: 'Content-Language',
                val: $contentType->languageCode,
            );
        }
        $this->setHeader(
            key: 'Strict-Transport-Security',
            val: 'max-age=' . HttpResponse::HSTS_MAX_AGE,
        );
        $this->setHeader(
            key: 'X-Content-Type-Options',
            val: 'nosniff',
        );
        $this->setHeader(
            key: 'Referrer-Policy',
            val: 'strict-origin-when-cross-origin',
        );
    }

    public function setHeader(string $key, string $val): void
    {
        $this->headers[$key] = $val;
    }

    /**
     * Whether the client has the current version already (RFC 9110, section 13.1). If the request has an
     * `If-None-Match` header, only it counts: `*` or one of its entity tags is the ETag (weak comparison, with or
     * without quotes). Otherwise its `If-Modified-Since` header is not before the time of the last modification.
     *
     * @param string $eTag The entity tag without quotes
     */
    public static function isNotModified(HttpRequest $httpRequest, string $eTag, int $lastModifiedTimeStamp): bool
    {
        if ($httpRequest->getHeader(name: 'If-None-Match') !== null) {
            return HttpResponse::isETagCurrent(httpRequest: $httpRequest, eTag: $eTag);
        }
        $modifiedSince = $httpRequest->getHeader(name: 'If-Modified-Since');
        if ($modifiedSince === null) {
            return false;
        }
        $modifiedSinceTimeStamp = strtotime(datetime: $modifiedSince);

        return $modifiedSinceTimeStamp !== false && $modifiedSinceTimeStamp >= $lastModifiedTimeStamp;
    }

    /**
     * Whether `*` or one of the entity tags of the `If-None-Match` header is the ETag (weak comparison, with or without
     * quotes). Apache appends the compression to the ETag of a compressed response (`"abc-gzip"`, `-br`, `-deflate`)
     * and gets it back so from the browser: that is the same ETag.
     *
     * @param string $eTag The entity tag without quotes
     */
    public static function isETagCurrent(HttpRequest $httpRequest, string $eTag): bool
    {
        $ifNoneMatch = $httpRequest->getHeader(name: 'If-None-Match');
        if ($ifNoneMatch === null) {
            return false;
        }
        foreach (explode(separator: ',', string: $ifNoneMatch) as $entityTag) {
            $entityTag = trim(string: $entityTag);
            if ($entityTag === '*') {
                return true;
            }
            if (str_starts_with(haystack: $entityTag, needle: 'W/')) {
                $entityTag = substr(string: $entityTag, offset: 2);
            }
            $entityTag = trim(string: $entityTag, characters: '"');
            if (in_array(
                needle: $entityTag,
                haystack: [$eTag, $eTag . '-gzip', $eTag . '-br', $eTag . '-deflate'],
                strict: true,
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * The 304 response to a request whose browser has the current version of a generated page: only the ETag and the
     * caching header. No Content-Security-Policy: the browser keeps the stored one, whose nonce belongs to the stored
     * page (a new nonce would block its scripts).
     *
     * @param string $eTag The entity tag without quotes
     */
    public static function createNotModifiedResponse(string $eTag): HttpResponse
    {
        $httpResponse = HttpResponse::createStatusResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_MODIFIED);
        $httpResponse->setHeader(key: 'Cache-Control', val: 'private, no-cache');
        $httpResponse->setHeader(key: 'Etag', val: '"' . $eTag . '"');

        return $httpResponse;
    }

    public function getHeader(string $key): ?string
    {
        return array_key_exists(key: $key, array: $this->headers) ? $this->headers[$key] : null;
    }

    /**
     * @return array<string, string>
     */
    public function listHeaders(): array
    {
        return $this->headers;
    }

    /**
     * The content of a response created from a string; `null` for a response from a file. A 304 response has the
     * content too, but `sendAndExit()` does not send it.
     */
    public function getContentString(): ?string
    {
        return $this->contentString;
    }

    /**
     * The path of the file of a response created from a file; `null` for a response from a string or without content.
     */
    public function getContentFilePath(): ?string
    {
        return $this->contentFilePath;
    }

    /**
     * Sends the status, the headers and the content (none for a 304), then ends the script.
     */
    public function sendAndExit(ResponseSender $responseSender = new NativeResponseSender()): never
    {
        $responseSender->send(httpResponse: $this);
    }

    /**
     * A response with the status and a `Location` header (absolute URI) and no content, so that nothing else than
     * these is sent.
     */
    public static function createRedirectResponse(
        string $relativeOrAbsoluteUri,
        HttpRequest $httpRequest,
        HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_SEE_OTHER,
    ): HttpResponse {
        $httpResponse = HttpResponse::createStatusResponse(httpStatusCode: $httpStatusCode);
        $httpResponse->setHeader(
            key: 'Location',
            val: UrlHelper::generateAbsoluteUri(
                relativeOrAbsoluteUri: $relativeOrAbsoluteUri,
                httpRequest: $httpRequest,
            ),
        );

        return $httpResponse;
    }

    /**
     * Sends a redirect and ends the script. The session cookie of the given handler is sent with the redirect, so it
     * is changed to SameSite=Lax temporarily and comes back from other sites.
     */
    public static function redirectAndExit(
        string $relativeOrAbsoluteUri,
        HttpRequest $httpRequest,
        HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_SEE_OTHER,
        ?AbstractSessionHandler $sameSiteLaxSessionHandler = null,
        ResponseSender $responseSender = new NativeResponseSender(),
    ): never {
        $sameSiteLaxSessionHandler?->changeCookieSameSiteToLax();
        HttpResponse::createRedirectResponse(
            relativeOrAbsoluteUri: $relativeOrAbsoluteUri,
            httpRequest: $httpRequest,
            httpStatusCode: $httpStatusCode,
        )->sendAndExit(responseSender: $responseSender);
    }

    /**
     * @param ?string $languageCode Language of the content, sent as `Content-Language` (none for `null`)
     * @param ?string $eTag The version of a page without personal data: the browser may store it and revalidate it
     *                      (`private, no-cache`); `null` for `private, no-store`
     *
     * @throws LogicException if a policy is given without the nonce of the request
     */
    public static function createHtmlResponse(
        HttpStatusCodeEnum $httpStatusCode,
        string $htmlContent,
        ?CspPolicySettings $cspPolicySettings,
        ?string $nonce,
        HttpRequest $httpRequest,
        ?string $languageCode = null,
        ?string $eTag = null,
    ): HttpResponse {
        $httpResponse = HttpResponse::createGeneratedContentResponse(
            httpStatusCode: $httpStatusCode,
            contentType: ContentType::createHtml(languageCode: $languageCode),
            contentString: $htmlContent,
            eTag: $eTag,
        );
        if ($cspPolicySettings === null) {
            return $httpResponse;
        }
        if ($nonce === null) {
            throw new LogicException(message: 'A Content-Security-Policy needs the nonce of the request.');
        }
        $httpResponse->setHeader(
            key: 'Content-Security-Policy',
            val: $cspPolicySettings->getHttpHeaderDataString(nonce: $nonce, httpRequest: $httpRequest),
        );

        return $httpResponse;
    }

    /**
     * @param ?string $eTag See `createHtmlResponse()`
     *
     * @throws LogicException for an HTML content type (use `createHtmlResponse()`)
     */
    public static function createResponseFromString(
        HttpStatusCodeEnum $httpStatusCode,
        string $contentString,
        ContentType $contentType,
        HttpRequest $httpRequest,
        ?string $eTag = null,
    ): HttpResponse {
        if ($contentType->isHtml()) {
            throw new LogicException(message: 'Use HttpResponse::createHtmlResponse() instead');
        }

        return HttpResponse::createGeneratedContentResponse(
            httpStatusCode: $httpStatusCode,
            contentType: $contentType,
            contentString: $contentString,
            eTag: $eTag,
        );
    }

    /**
     * A 404 response (only the status) if the path is no file, a 403 response if it is not readable.
     *
     * @param int $maxAge Seconds the browser may use the file without asking again (`max-age`); 0: it asks every time
     * @param bool $isPublic Shared caches (proxies, CDN) may store the file too; only for files without personal data
     * @param bool $isImmutable The file never changes under this URL (a version in the URL, e.g. `?v=20260922`)
     *
     * @throws RuntimeException if the modification time or the size of the readable file cannot be read
     */
    public static function createResponseFromFilePath(
        string $absolutePathToFile,
        ?bool $forceDownload,
        ?string $individualFileName,
        int $maxAge,
        HttpRequest $httpRequest,
        Clock $clock = new SystemClock(),
        bool $isPublic = false,
        bool $isImmutable = false,
    ): HttpResponse {
        $realPath = realpath(path: $absolutePathToFile);
        if ($realPath === false || !is_file(filename: $realPath)) {
            return HttpResponse::createStatusResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND);
        }
        if (!is_readable(filename: $realPath)) {
            return HttpResponse::createStatusResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_FORBIDDEN);
        }
        $lastModifiedTimeStamp = filemtime(filename: $realPath);
        $fileSize = filesize(filename: $realPath);
        if ($lastModifiedTimeStamp === false || $fileSize === false) {
            throw new RuntimeException(message: 'Cannot read modification time and size of the file ' . $realPath);
        }
        $fileName = $individualFileName ?? basename(path: $realPath);
        $extension = FileHandler::getExtension(filename: $fileName);
        $contentType = ContentType::createFromFileExtension(extension: $extension);
        $forceDownload ??= $contentType->forceDownloadByDefault;
        $eTag = HttpResponse::createETag(content: $lastModifiedTimeStamp . '-' . $fileSize . '-' . $realPath);
        $cacheControl = ($isPublic ? 'public' : 'private') . ', ' . ($maxAge > 0 ? 'max-age=' . $maxAge : 'no-cache')
            . ($isImmutable ? ', immutable' : '');
        $httpResponse = new HttpResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_OK, contentFilePath: $realPath);
        $httpResponse->setHeader(key: 'Etag', val: '"' . $eTag . '"');
        $httpResponse->setHeader(
            key: 'Last-Modified',
            val: gmdate(format: 'D, d M Y H:i:s', timestamp: $lastModifiedTimeStamp) . ' GMT',
        );
        $httpResponse->setHeader(key: 'Cache-Control', val: $cacheControl);
        $httpResponse->setHeader(
            key: 'Expires',
            val: gmdate(format: 'D, d M Y H:i:s', timestamp: $clock->now()->getTimestamp() + $maxAge) . ' GMT',
        );
        if (HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: $eTag,
            lastModifiedTimeStamp: $lastModifiedTimeStamp,
        )) {
            $httpResponse->httpStatusCode = HttpStatusCodeEnum::HTTP_NOT_MODIFIED;

            return $httpResponse;
        }
        if ($forceDownload) {
            $httpResponse->setHeader(key: 'Content-Description', val: 'File Transfer');
            $httpResponse->setHeader(key: 'Content-Disposition', val: 'attachment; filename="' . $fileName . '"');
        }
        $httpResponse->setContentTypeAndSecurityHeaders(contentType: $contentType);
        $httpResponse->setHeader(key: 'Content-Length', val: (string) $fileSize);

        return $httpResponse;
    }

    private static function createETag(string $content): string
    {
        return hash(algo: 'sha256', data: $content);
    }

    public function removeHeader(string $key): bool
    {
        if (array_key_exists(key: $key, array: $this->headers)) {
            unset($this->headers[$key]);

            return true;
        }

        return false;
    }
}
