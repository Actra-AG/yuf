<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ContentType;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\NativeResponseSender;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Covers `writeContent()` (the output). Not covered: `send()`, it calls `header()` and `exit`.
 */
final class NativeResponseSenderTest extends TestCase
{
    private string $file;

    #[Override]
    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-sender-'
            . bin2hex(string: random_bytes(length: 8)) . '.txt';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file(filename: $this->file)) {
            unlink(filename: $this->file);
        }
    }

    private function capturedOutput(HttpResponse $httpResponse): string
    {
        ob_start();
        new NativeResponseSender()->writeContent(httpResponse: $httpResponse);
        $output = ob_get_clean();
        $this->assertIsString($output);

        return $output;
    }

    public function testWritesTheStringContent(): void
    {
        $httpResponse = HttpResponse::createResponseFromString(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            contentString: 'Hello',
            contentType: ContentType::createTxt(),
            httpRequest: HttpRequestFactory::create(),
        );

        $this->assertSame('Hello', $this->capturedOutput(httpResponse: $httpResponse));
    }

    public function testWritesAFileInChunks(): void
    {
        $content = str_repeat(string: 'abcdefgh', times: 3000);
        file_put_contents(filename: $this->file, data: $content);
        $httpResponse = HttpResponse::createResponseFromFilePath(
            absolutePathToFile: $this->file,
            forceDownload: false,
            individualFileName: null,
            maxAge: 0,
            httpRequest: HttpRequestFactory::create(),
        );

        $this->assertSame($content, $this->capturedOutput(httpResponse: $httpResponse));
    }

    public function testWritesNothingForANotModifiedResponse(): void
    {
        $httpResponse = HttpResponse::createResponseFromString(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            contentString: 'Hello',
            contentType: ContentType::createTxt(),
            httpRequest: HttpRequestFactory::create(
                headers: ['If-None-Match' => hash(algo: 'sha256', data: 'Hello')],
            ),
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_MODIFIED, $httpResponse->httpStatusCode);
        $this->assertSame('', $this->capturedOutput(httpResponse: $httpResponse));
    }

    public function testWritesNothingForARedirect(): void
    {
        $httpResponse = HttpResponse::createRedirectResponse(
            relativeOrAbsoluteUri: '/login',
            httpRequest: HttpRequestFactory::create(),
        );

        $this->assertSame('', $this->capturedOutput(httpResponse: $httpResponse));
    }
}
