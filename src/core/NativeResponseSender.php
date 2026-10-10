<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use Closure;
use Override;

/**
 * Sends the response with `header()` and `echo` and ends the script with `exit`. With PHP-FPM, the request is finished
 * with `fastcgi_finish_request()` before. `send()` itself is not unit tested (it calls `header()`,
 * `session_write_close()`, `fastcgi_finish_request()` and `exit`); the output of the content is `writeContent()`.
 */
final class NativeResponseSender implements ResponseSender
{
    #[Override]
    public function send(HttpResponse $httpResponse): never
    {
        header(header: $httpResponse->httpStatusCode->getStatusHeader());
        foreach ($httpResponse->listHeaders() as $key => $val) {
            header(header: $key . ': ' . $val);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            // A view that sends its own response (a download, sendAndExit()) must not keep the session lock while the
            // client receives the content: parallel requests of the user would wait
            session_write_close();
        }
        if (
            $httpResponse->httpStatusCode !== HttpStatusCodeEnum::HTTP_NOT_MODIFIED
            && $httpResponse->getContentString() === null
            && $httpResponse->getContentFilePath() !== null
        ) {
            // A file is streamed: no output buffer of the application may hold it
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
        $this->writeContent(httpResponse: $httpResponse);
        if (function_exists(function: 'fastcgi_finish_request')) {
            // The client has the complete response now (PHP-FPM): writing the session, destructors and the shutdown
            // functions run after that, so they do not delay the response. Nothing can be output after this call.
            fastcgi_finish_request();
        }
        exit;
    }

    /**
     * Registers the callback as shutdown function: PHP runs it after `exit`, which is after `fastcgi_finish_request()`
     * (see `send()`). Not unit tested (the shutdown functions of the test process).
     */
    #[Override]
    public function afterResponse(Closure $callback): void
    {
        register_shutdown_function(callback: $callback);
    }

    /**
     * Prints the string content, or streams the file, of the response; nothing for a 304 and for a response without
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
        fpassthru(stream: $file);
        fclose(stream: $file);
    }
}
