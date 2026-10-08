<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use Override;

/**
 * Sends the response with `header()` and `echo` and ends the script with `exit`. `send()` itself is not unit tested
 * (it calls `header()` and `exit`); the output of the content is `writeContent()`.
 */
final class NativeResponseSender implements ResponseSender
{
    private const int FILE_CHUNK_SIZE = 8192;

    #[Override]
    public function send(HttpResponse $httpResponse): never
    {
        header(header: $httpResponse->httpStatusCode->getStatusHeader());
        foreach ($httpResponse->listHeaders() as $key => $val) {
            header(header: $key . ': ' . $val);
        }
        if (
            $httpResponse->httpStatusCode !== HttpStatusCodeEnum::HTTP_NOT_MODIFIED
            && $httpResponse->getContentString() === null
            && $httpResponse->getContentFilePath() !== null
            && ob_get_level() > 0
        ) {
            // A file is streamed: the output buffer of the application must not hold it
            ob_end_clean();
        }
        $this->writeContent(httpResponse: $httpResponse);
        exit;
    }

    /**
     * Prints the string content, or the file in chunks, of the response; nothing for a 304 and for a response without
     * content (a redirect, a 404).
     */
    public function writeContent(HttpResponse $httpResponse): void
    {
        if ($httpResponse->httpStatusCode === HttpStatusCodeEnum::HTTP_NOT_MODIFIED) {
            return;
        }
        $contentString = $httpResponse->getContentString();
        if ($contentString !== null) {
            echo $contentString;

            return;
        }
        $filePath = $httpResponse->getContentFilePath();
        if ($filePath === null) {
            return;
        }
        $file = fopen(
            filename: $filePath,
            mode: 'rb',
        );
        if ($file === false) {
            return;
        }
        while (!feof(stream: $file)) {
            echo fread(
                stream: $file,
                length: NativeResponseSender::FILE_CHUNK_SIZE,
            );
            flush();
        }
        fclose(stream: $file);
    }
}
