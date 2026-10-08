<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\HttpRequest;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\session\Session;
use Throwable;

/**
 * Everything the debug page shows about an error and its request, as plain text. It contains stack traces, file
 * paths, request data and the session: only for debug mode, never for production. The values are plain text; the
 * debug page adds them with `addText()`, so they are escaped.
 *
 * @internal
 */
final readonly class ExceptionDebugInfo
{
    public function __construct(
        public string $title,
        public string $errorType,
        public string $errorMessage,
        public string $errorFile,
        public string $errorLine,
        public int|string $errorCode,
        public string $backtrace,
        public string $queryParameters,
        public string $postParameters,
        public string $files,
        public string $session,
    ) {}

    /**
     * @param ?Session $session `null` without sessions
     */
    public static function create(
        Throwable $throwable,
        HttpRequest $httpRequest,
        ?Session $session,
    ): ExceptionDebugInfo {
        // The cause is the more useful origin (a wrapped database or library error)
        $realException = $throwable->getPrevious() ?? $throwable;

        return new ExceptionDebugInfo(
            title: ErrorKindEnum::fromThrowable(throwable: $throwable)->getTitle(),
            errorType: $throwable::class,
            errorMessage: $realException->getMessage(),
            errorFile: $realException->getFile(),
            errorLine: (string) $realException->getLine(),
            errorCode: $realException->getCode(),
            backtrace: $realException->getTraceAsString(),
            queryParameters: var_export(value: $httpRequest->getQueryParameters(), return: true),
            postParameters: var_export(value: $httpRequest->getPostParameters(), return: true),
            files: var_export(value: $httpRequest->getRawFiles(), return: true),
            session: $session === null ? '' : var_export(value: $session->export(), return: true),
        );
    }

    /**
     * The values by the identifier of the debug page template and of the JSON / text answer.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'errorType' => $this->errorType,
            'errorMessage' => $this->errorMessage,
            'errorFile' => $this->errorFile,
            'errorLine' => $this->errorLine,
            'errorCode' => (string) $this->errorCode,
            'backtrace' => $this->backtrace,
            'vardump_get' => $this->queryParameters,
            'vardump_post' => $this->postParameters,
            'vardump_file' => $this->files,
            'vardump_sess' => $this->session,
        ];
    }

    /**
     * Adds the values as plain text (escaped when the template outputs them).
     */
    public function addTo(HtmlReplacementCollection $replacements): void
    {
        foreach ($this->toArray() as $identifier => $text) {
            $replacements->addText(identifier: $identifier, text: $text);
        }
    }
}
