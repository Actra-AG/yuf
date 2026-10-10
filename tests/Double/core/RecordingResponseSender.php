<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\ResponseSender;
use Closure;
use LogicException;
use Override;

/**
 * Records the response instead of sending it and throws `ResponseSentException` (a `never` method may throw), so the
 * code after the sending does not run, like after `exit`.
 */
final class RecordingResponseSender implements ResponseSender
{
    public ?HttpResponse $sentResponse = null;
    /** @var list<Closure(): void> */
    private array $afterResponseCallbacks = [];

    /**
     * Runs the action with a new sender; the action has to send a response, which is returned.
     *
     * @param Closure(ResponseSender): void $action
     *
     * @throws LogicException if the action returns without sending a response
     */
    public static function capture(Closure $action): HttpResponse
    {
        $responseSender = new RecordingResponseSender();
        try {
            $action($responseSender);
        } catch (ResponseSentException) {
            // The double throws instead of ending the process
        }
        if ($responseSender->sentResponse === null) {
            throw new LogicException(message: 'The action did not send a response.');
        }

        return $responseSender->sentResponse;
    }

    #[Override]
    public function send(HttpResponse $httpResponse): never
    {
        $this->sentResponse = $httpResponse;

        throw new ResponseSentException(message: 'The response was sent.');
    }

    #[Override]
    public function afterResponse(Closure $callback): void
    {
        $this->afterResponseCallbacks[] = $callback;
    }

    /**
     * Runs (once, in registration order) the callbacks that the code registered with `afterResponse()`, like PHP
     * does at the end of the script; the test calls it when it wants to see what happens after the response.
     */
    public function runAfterResponseCallbacks(): void
    {
        $callbacks = $this->afterResponseCallbacks;
        $this->afterResponseCallbacks = [];
        foreach ($callbacks as $callback) {
            $callback();
        }
    }

    public function countAfterResponseCallbacks(): int
    {
        return count(value: $this->afterResponseCallbacks);
    }
}
