<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\RecordingResponseSender;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\ResponseSentException;
use ArrayObject;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The test double of yuf: records the response, and the after-response callbacks run only when the test says so.
 */
final class RecordingResponseSenderTest extends TestCase
{
    public function testSendRecordsTheResponseAndThrows(): void
    {
        $responseSender = new RecordingResponseSender();
        $httpResponse = HttpResponse::createStatusResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND);

        try {
            $responseSender->send(httpResponse: $httpResponse);
        } catch (ResponseSentException) {
            // Instead of ending the process
        }

        $this->assertSame($httpResponse, $responseSender->sentResponse);
    }

    public function testCaptureReturnsTheSentResponse(): void
    {
        $httpResponse = HttpResponse::createStatusResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND);

        $sentResponse = RecordingResponseSender::capture(
            action: static function (ResponseSender $responseSender) use ($httpResponse): void {
                $responseSender->send(httpResponse: $httpResponse);
            },
        );

        $this->assertSame($httpResponse, $sentResponse);
    }

    public function testCaptureWithoutResponseThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The action did not send a response.');

        RecordingResponseSender::capture(action: static function (ResponseSender $responseSender): void {});
    }

    public function testCallbacksRunOnDemandInRegistrationOrderAndOnlyOnce(): void
    {
        $responseSender = new RecordingResponseSender();
        $calls = new ArrayObject();
        $responseSender->afterResponse(callback: function () use ($calls): void {
            $calls->append('first');
        });
        $responseSender->afterResponse(callback: function () use ($calls): void {
            $calls->append('second');
        });

        $this->assertCount(0, $calls);
        $this->assertSame(2, $responseSender->countAfterResponseCallbacks());

        $responseSender->runAfterResponseCallbacks();
        $responseSender->runAfterResponseCallbacks();

        $this->assertSame(['first', 'second'], $calls->getArrayCopy());
        $this->assertSame(0, $responseSender->countAfterResponseCallbacks());
    }
}
