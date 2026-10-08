<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\request;

use actra\yuf\request\RequestBody;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the static RequestBody before the redesign (docs/http-request/plan.md, step 1).
 *
 * php://input cannot be fed in a test (it is empty in the CLI) and RequestBody caches it statically, so only the
 * empty body can be characterized. The parsing is covered by JsonRequestBodyTest.
 */
final class RequestBodyTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBodyIsEmptyInTheCli(): void
    {
        $this->assertSame('', RequestBody::getData());
    }
}
