<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

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
}
