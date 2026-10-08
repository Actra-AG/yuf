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
 * A response with its headers; created by the `create…()` factories, sent with `sendAndExit()`. A request that
 * already has the current version (`If-None-Match`, `If-Modified-Since`) gets a 304 response without content.
 */
final class HttpResponse
{
    /** One year, independent of how long the response may be cached (max-age=0 would remove HSTS in the browser). */
    private const int HSTS_MAX_AGE = 31536000;

    /** @var array<string, string> */
    private array $headers = [];

    private function __construct(
        HttpRequest $httpRequest,
        string $eTag,
        int $lastModifiedTimeStamp,
        public private(set) HttpStatusCodeEnum $httpStatusCode,
        ?string $downloadFileName,
        ContentType $contentType,
        private readonly ?string $contentString = null,
        private readonly ?string $contentFilePath = null,
    ) {
        $this->setHeader(
            key: 'Etag',
            val: $eTag,
        );
        $this->setHeader(
            key: 'Last-Modified',
            val: gmdate(format: 'r', timestamp: $lastModifiedTimeStamp),
        );
        $this->setHeader(
            key: 'Cache-Control',
            val: 'private, must-revalidate',
        );
        if ($downloadFileName !== null) {
            $this->setHeader(
                key: 'Content-Description',
                val: 'File Transfer',
            );
            $this->setHeader(
                key: 'Content-Disposition',
                val: 'attachment; filename="' . $downloadFileName . '"',
            );
        }
        if (HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: $eTag,
            lastModifiedTimeStamp: $lastModifiedTimeStamp,
        )) {
            $this->httpStatusCode = HttpStatusCodeEnum::HTTP_NOT_MODIFIED;
            $this->setHeader(
                key: 'Connection',
                val: 'Close',
            ); // Prevent keep-alive

            return;
        }
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
     * Whether the client has the current version already: its `If-None-Match` header is the ETag or its
     * `If-Modified-Since` header is the time of the last modification.
     */
    public static function isNotModified(HttpRequest $httpRequest, string $eTag, int $lastModifiedTimeStamp): bool
    {
        if ($httpRequest->getHeader(name: 'If-None-Match') === $eTag) {
            return true;
        }
        $modifiedSince = $httpRequest->getHeader(name: 'If-Modified-Since');

        return $modifiedSince !== null && strtotime(datetime: $modifiedSince) === $lastModifiedTimeStamp;
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
     * Sends the status, the headers and the content (none for a 304), then ends the script.
     */
    public function sendAndExit(): void
    {
        header(header: $this->httpStatusCode->getStatusHeader());
        foreach ($this->headers as $key => $val) {
            header(header: $key . ': ' . $val);
        }
        if ($this->httpStatusCode === HttpStatusCodeEnum::HTTP_NOT_MODIFIED) {
            exit;
        }
        if ($this->contentString !== null) {
            echo $this->contentString;
            exit;
        }
        if ($this->contentFilePath !== null) {
            $this->sendFileAndExit(filePath: $this->contentFilePath);
        }
        exit;
    }

    private function sendFileAndExit(string $filePath): never
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        $file = fopen(
            filename: $filePath,
            mode: 'rb',
        );
        if ($file === false) {
            exit;
        }
        while (!feof(stream: $file)) {
            echo fread(
                stream: $file,
                length: 8192,
            );
            flush();
        }
        fclose(stream: $file);
        exit;
    }

    public static function redirectAndExit(
        string $relativeOrAbsoluteUri,
        HttpRequest $httpRequest,
        HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_SEE_OTHER,
        ?AbstractSessionHandler $sameSiteLaxSessionHandler = null,
    ): void {
        // The session cookie is sent with the redirect: temporarily Lax, so it comes back from other sites
        $sameSiteLaxSessionHandler?->changeCookieSameSiteToLax();
        header(header: $httpStatusCode->getStatusHeader());
        header(header: 'Location: ' . UrlHelper::generateAbsoluteUri(
            relativeOrAbsoluteUri: $relativeOrAbsoluteUri,
            httpRequest: $httpRequest,
        ));
        exit;
    }

    /**
     * @param ?string $languageCode Language of the content, sent as `Content-Language` (none for `null`)
     *
     * @throws LogicException if a policy is given without the nonce of the request
     */
    public static function createHtmlResponse(
        HttpStatusCodeEnum $httpStatusCode,
        string $htmlContent,
        ?CspPolicySettings $cspPolicySettings,
        ?string $nonce,
        HttpRequest $httpRequest,
        Clock $clock = new SystemClock(),
        ?string $languageCode = null,
    ): HttpResponse {
        $httpResponse = new HttpResponse(
            httpRequest: $httpRequest,
            eTag: HttpResponse::createETag(content: $htmlContent),
            lastModifiedTimeStamp: $clock->now()->getTimestamp(),
            httpStatusCode: $httpStatusCode,
            downloadFileName: null,
            contentType: ContentType::createHtml(languageCode: $languageCode),
            contentString: $htmlContent,
            contentFilePath: null,
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
     * @throws LogicException for an HTML content type (use `createHtmlResponse()`)
     */
    public static function createResponseFromString(
        HttpStatusCodeEnum $httpStatusCode,
        string $contentString,
        ContentType $contentType,
        HttpRequest $httpRequest,
        Clock $clock = new SystemClock(),
    ): HttpResponse {
        if ($contentType->isHtml()) {
            throw new LogicException(message: 'Use HttpResponse::createHtmlResponse() instead');
        }

        return new HttpResponse(
            httpRequest: $httpRequest,
            eTag: HttpResponse::createETag(content: $contentString),
            lastModifiedTimeStamp: $clock->now()->getTimestamp(),
            httpStatusCode: $httpStatusCode,
            downloadFileName: null,
            contentType: $contentType,
            contentString: $contentString,
            contentFilePath: null,
        );
    }

    /**
     * Sends status 404 or 403 and ends the script if the path is no readable file.
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
    ): HttpResponse {
        $realPath = realpath(path: $absolutePathToFile);
        if ($realPath === false || !is_file(filename: $realPath)) {
            header(header: HttpStatusCodeEnum::HTTP_NOT_FOUND->getStatusHeader());
            exit;
        }
        if (!is_readable(filename: $realPath)) {
            header(header: HttpStatusCodeEnum::HTTP_FORBIDDEN->getStatusHeader());
            exit;
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
        $httpResponse = new HttpResponse(
            httpRequest: $httpRequest,
            eTag: HttpResponse::createETag(content: $lastModifiedTimeStamp . $realPath),
            lastModifiedTimeStamp: $lastModifiedTimeStamp,
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            downloadFileName: $forceDownload ? $fileName : null,
            contentType: $contentType,
            contentString: null,
            contentFilePath: $realPath,
        );
        $httpResponse->setHeader(
            key: 'Content-Length',
            val: (string) $fileSize,
        );
        $httpResponse->setHeader(
            key: 'Expires',
            val: gmdate(format: 'r', timestamp: $clock->now()->getTimestamp() + $maxAge),
        );

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
