<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\Core;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\Logger;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;

/**
 * The dependencies of the request that `Core` hands to the exception handler.
 */
final readonly class ExceptionHandlerContext
{
    public function __construct(
        public Logger $logger,
        public CspNonce $cspNonce,
        public ?CspPolicySettings $cspPolicySettings,
        public bool $isDebug,
        public Core $core,
        public HttpRequest $httpRequest,
    ) {}
}
