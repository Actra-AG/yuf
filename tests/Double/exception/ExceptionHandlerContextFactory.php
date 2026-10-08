<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\exception;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\Logger;
use actra\yuf\core\ResponseSender;
use actra\yuf\exception\ExceptionHandlerContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
use actra\yuf\tests\Double\template\TemplateEngineFactory;

/**
 * Builds the context of an exception handler like `Core` does, with the error pages of
 * tests/Fixture/exception/error_docs/ and a template cache in the given directory.
 */
final class ExceptionHandlerContextFactory
{
    public const string NONCE = 'test-nonce';

    public static function fixtureDirectory(): string
    {
        return dirname(path: __DIR__, levels: 2) . '/Fixture/exception/';
    }

    public static function create(
        Logger $logger,
        string $cacheDirectory,
        bool $isDebug = false,
        ?CspPolicySettings $cspPolicySettings = new CspPolicySettings(),
        ?HttpRequest $httpRequest = null,
        ?string $errorDocsDirectory = null,
        LanguageCollection $availableLanguages = new LanguageCollection(),
        ?ResponseSender $responseSender = null,
    ): ExceptionHandlerContext {
        return new ExceptionHandlerContext(
            logger: $logger,
            cspNonce: new CspNonce(value: ExceptionHandlerContextFactory::NONCE),
            cspPolicySettings: $cspPolicySettings,
            isDebug: $isDebug,
            httpRequest: $httpRequest ?? HttpRequestFactory::create(),
            errorDocsDirectory: $errorDocsDirectory
                ?? ExceptionHandlerContextFactory::fixtureDirectory() . 'error_docs/',
            copyright: '2020-2026',
            availableLanguages: $availableLanguages,
            createTemplateEngine: static fn(LocaleHandler $localeHandler) => TemplateEngineFactory::create(
                cacheDirectory: $cacheDirectory,
                templateBaseDirectory: ExceptionHandlerContextFactory::fixtureDirectory(),
                localeHandler: $localeHandler,
            ),
            responseSender: $responseSender ?? new RecordingResponseSender(),
        );
    }
}
