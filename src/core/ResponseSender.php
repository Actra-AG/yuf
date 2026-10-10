<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use Closure;

/**
 * Sends a response to the client and ends the process. Production code uses `NativeResponseSender`; tests pass a
 * double that records the response (and throws, which a `never` method may do).
 */
interface ResponseSender
{
    /**
     * Sends the status, the headers and the content of the response (the string or the file; nothing for a 304), then
     * ends the process.
     */
    public function send(HttpResponse $httpResponse): never;

    /**
     * Runs the callback after the response was sent: for work the client must not wait for (a mail, a log entry, a
     * cleanup). With PHP-FPM the client has the complete response then (`NativeResponseSender` finishes the request
     * before it ends the process); with other SAPIs the callback runs at the end of the script and still delays the
     * connection. Callbacks run in the order they were registered, and at the end of the script even if no response
     * is sent through the sender.
     *
     * The session is closed by then: a callback must not write it (`LogicException`). A callback that throws must
     * not be relied on to report: catch and log the failure inside the callback.
     *
     * @param Closure(): void $callback
     */
    public function afterResponse(Closure $callback): void;
}
