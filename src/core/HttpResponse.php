<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\common\FileHandler;
use actra\yuf\common\UrlHelper;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\session\AbstractSessionHandler;
use LogicException;

class HttpResponse
{
    /** One year, independent of how long the response may be cached (max-age=0 would remove HSTS in the browser). */
    private const int HSTS_MAX_AGE = 31536000;

    private array $headers = [];

    private function __construct(
        HttpRequest              $httpRequest,
        string                   $eTag,
        int                      $lastModifiedTimeStamp,
        private HttpStatusCodeEnum   $httpStatusCode,
        ?string                  $downloadFileName,
        ContentType              $contentType,
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
            $this->sendAndExit();
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

    public function sendAndExit(): void
    {
        header(header: $this->httpStatusCode->getStatusHeader());
        foreach ($this->headers as $key => $val) {
            header(header: $key . ': ' . $val);
        }
        if ($this->contentString !== null) {
            echo $this->contentString;
            exit;
        }
        if ($this->contentFilePath !== null) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            $file = fopen(
                filename: $this->contentFilePath,
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
    }

    public static function redirectAndExit(
        string         $relativeOrAbsoluteUri,
        HttpRequest    $httpRequest,
        HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_SEE_OTHER,
        bool           $setSameSiteCookieTemporaryToLax = false,
    ): void {
        if ($setSameSiteCookieTemporaryToLax) {
            AbstractSessionHandler::getSessionHandler()->changeCookieSameSiteToLax();
        }
        header(header: $httpStatusCode->getStatusHeader());
        header(header: 'Location: ' . UrlHelper::generateAbsoluteUri(
            relativeOrAbsoluteUri: $relativeOrAbsoluteUri,
            httpRequest: $httpRequest,
        ));
        exit;
    }

    public static function createHtmlResponse(
        HttpStatusCodeEnum          $httpStatusCode,
        string                  $htmlContent,
        ?CspPolicySettings $cspPolicySettings,
        ?string                 $nonce,
        HttpRequest        $httpRequest,
    ): HttpResponse {
        $httpResponse = new HttpResponse(
            httpRequest: $httpRequest,
            eTag: md5($htmlContent),
            lastModifiedTimeStamp: time(),
            httpStatusCode: $httpStatusCode,
            downloadFileName: null,
            contentType: ContentType::createHtml(),
            contentString: $htmlContent,
            contentFilePath: null,
        );
        if ($cspPolicySettings !== null) {
            $httpResponse->setHeader(
                key: 'Content-Security-Policy',
                val: $cspPolicySettings->getHttpHeaderDataString(nonce: $nonce, httpRequest: $httpRequest),
            );
        }

        return $httpResponse;
    }

    public static function createResponseFromString(
        HttpStatusCodeEnum $httpStatusCode,
        string         $contentString,
        ContentType    $contentType,
        HttpRequest    $httpRequest,
    ): HttpResponse {
        if ($contentType->isHtml()) {
            throw new LogicException(message: 'Use HttpResponse::createHtmlResponse() instead');
        }

        return new HttpResponse(
            httpRequest: $httpRequest,
            eTag: md5(string: $contentString),
            lastModifiedTimeStamp: time(),
            httpStatusCode: $httpStatusCode,
            downloadFileName: null,
            contentType: $contentType,
            contentString: $contentString,
            contentFilePath: null,
        );
    }

    public static function createResponseFromFilePath(
        string  $absolutePathToFile,
        ?bool   $forceDownload,
        ?string $individualFileName,
        int     $maxAge,
        HttpRequest $httpRequest,
    ): HttpResponse {
        $realPath = realpath(path: $absolutePathToFile);

        if (!is_readable(filename: $realPath)) {
            header(header: HttpStatusCodeEnum::HTTP_FORBIDDEN->getStatusHeader());
            exit;
        }
        if (
            $realPath === false
            || !is_file(filename: $realPath)
        ) {
            header(header: HttpStatusCodeEnum::HTTP_NOT_FOUND->getStatusHeader());
            exit;
        }
        $lastModifiedTimeStamp = filemtime(filename: $realPath);
        $fileName = $individualFileName === null ? basename(path: $realPath) : $individualFileName;

        $contentType = ContentType::createFromFileExtension(
            extension: FileHandler::getExtension(filename: $fileName),
        );
        if ($forceDownload === null) {
            $forceDownload = $contentType->forceDownloadByDefault;
        }
        $httpResponse = new HttpResponse(
            httpRequest: $httpRequest,
            eTag: md5(string: $lastModifiedTimeStamp . $realPath),
            lastModifiedTimeStamp: $lastModifiedTimeStamp,
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            downloadFileName: ($forceDownload ? $fileName : null),
            contentType: $contentType,
            contentString: null,
            contentFilePath: $realPath,
        );
        $httpResponse->setHeader(
            key: 'Content-Length',
            val: (string) filesize(filename: $realPath),
        );
        $httpResponse->setHeader(
            key: 'Expires',
            val: gmdate(format: 'r', timestamp: time() + $maxAge),
        );

        return $httpResponse;
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
