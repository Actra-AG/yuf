<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\Logger;
use actra\yuf\core\NativeResponseSender;
use actra\yuf\core\ResponseSender;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\template\TemplateEngine;
use Closure;

/**
 * The dependencies of the request that `Core` hands to the exception handler.
 */
final readonly class ExceptionHandlerContext
{
    /**
     * @param string $errorDocsDirectory The directory of the error pages, with a trailing directory separator
     * @param string $copyright The copyright text of the error pages (`2026`, `2020-2026`)
     * @param Closure(LocaleHandler): TemplateEngine $createTemplateEngine Creates the template engine of the error
     *     pages (`Core::createTemplateEngine()`)
     * @param ResponseSender $responseSender Sends the response of `ExceptionHandler::handleException()`
     */
    public function __construct(
        public Logger $logger,
        public CspNonce $cspNonce,
        public ?CspPolicySettings $cspPolicySettings,
        public bool $isDebug,
        public HttpRequest $httpRequest,
        public string $errorDocsDirectory,
        public string $copyright,
        public LanguageCollection $availableLanguages,
        public Closure $createTemplateEngine,
        public ResponseSender $responseSender = new NativeResponseSender(),
    ) {}
}
