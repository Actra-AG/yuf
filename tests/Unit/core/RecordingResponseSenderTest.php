<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\tests\Double\core\RecordingResponseSender;
use ArrayObject;
use PHPUnit\Framework\TestCase;

/**
 * The after-response callbacks of the test double: registered, not run until the test says so.
 */
final class RecordingResponseSenderTest extends TestCase
{
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
