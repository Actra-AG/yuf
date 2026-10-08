<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\Logger;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlSnippet;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;

/**
 * Renders an error page: a template file of the error docs directory with the replacements of the error. A missing
 * file never shows its path to the user: debug mode shows it, production logs it and shows the short text of the
 * error.
 *
 * @internal
 */
final readonly class ErrorPageRenderer
{
    public function __construct(
        private string $errorDocsDirectory,
        private bool $isDebug,
        private Logger $logger,
    ) {}

    /**
     * @param string $htmlFileName The name of a file in the error docs directory (no path)
     * @param string $fallbackText Shown instead of the page in production if the file is missing
     *
     * @throws InvalidArgumentException if the file name contains a path
     */
    public function render(
        string $htmlFileName,
        HtmlReplacementCollection $replacements,
        TemplateEngine $templateEngine,
        string $fallbackText,
    ): string {
        if ($htmlFileName === '' || basename(path: $htmlFileName) !== $htmlFileName) {
            throw new InvalidArgumentException(
                message: 'The error page "' . $htmlFileName . '" must be a file name without path.',
            );
        }
        $contentPath = $this->errorDocsDirectory . $htmlFileName;
        if (!is_file(filename: $contentPath)) {
            return $this->renderMissingPage(contentPath: $contentPath, fallbackText: $fallbackText);
        }

        return new HtmlSnippet(
            htmlSnippetFilePath: $contentPath,
            replacements: $replacements,
        )->render(templateEngine: $templateEngine);
    }

    private function renderMissingPage(string $contentPath, string $fallbackText): string
    {
        $message = 'Missing error html file ' . $contentPath;
        if ($this->isDebug) {
            return HtmlEncoder::encode(value: $message);
        }
        $this->logger->logMessage(message: $message);

        return HtmlEncoder::encode(value: $fallbackText);
    }
}
