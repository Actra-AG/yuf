<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfHiddenFieldRenderer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateEngine;
use OutOfBoundsException;
use RuntimeException;

/**
 * The page of a request: the content file of the view inside a template, with the replacements of the view. Created
 * by `ContentHandler` once per request, views reach it with `BaseView::getHtmlDocument()`.
 *
 * The navigation elements of the output (`id="nav-<key>"`) get the class `active` for the keys that views register
 * with `setActiveHtmlId()`.
 */
final class HtmlDocument
{
    public readonly HtmlReplacementCollection $replacements;
    public string $templateDirectory;
    public string $contentFileDirectory;
    public string $templateName = 'default';
    public string $contentFileName;
    /** @var array<int, string> */
    private array $activeHtmlIds = [];

    public function __construct(
        private readonly HtmlDocumentSettings $settings,
        CspNonce $cspNonce,
        private readonly TemplateEngine $templateEngine,
        ?CsrfTokenSource $csrfTokenSource,
    ) {
        $this->templateDirectory = $settings->viewDirectory . 'templates/';
        $this->contentFileDirectory = $settings->viewDirectory . 'html/';
        $this->contentFileName = $settings->fileTitle . '.html';
        $this->replacements = new HtmlReplacementCollection();
        // Everything that comes from the request is text and escaped; only the CSRF field is HTML of ours
        $this->replacements->addText(identifier: 'bodyClassName', text: 'body-' . $settings->fileTitle);
        $this->replacements->addText(identifier: 'language', text: $settings->languageCode);
        $this->replacements->addHtml(identifier: 'charset', html: 'UTF-8');
        $this->replacements->addText(identifier: 'copyright', text: $settings->copyright);
        $this->replacements->addText(identifier: 'robots', text: $settings->robots);
        $this->replacements->addHtml(identifier: 'scripts', html: '');
        $this->replacements->addText(identifier: 'cspNonce', text: $cspNonce->value);
        // Built when a template uses it: the token needs the session, which only starts on its first access
        $this->replacements->addLazyHtml(
            identifier: 'csrfField',
            html: static fn(): string => CsrfHiddenFieldRenderer::render(csrfTokenSource: $csrfTokenSource),
        );
        $this->replacements->addText(identifier: 'requestedFileName', text: $settings->fileName);
    }

    public function setActiveHtmlId(int $key, string $val): void
    {
        $this->activeHtmlIds[$key] = $val;
    }

    public function isActiveHtmlIdSet(int $key): bool
    {
        return array_key_exists(key: $key, array: $this->activeHtmlIds);
    }

    /**
     * @throws OutOfBoundsException if no id is set for the key (check with `isActiveHtmlIdSet()`)
     */
    public function getActiveHtmlId(int $key): string
    {
        if (!array_key_exists(key: $key, array: $this->activeHtmlIds)) {
            throw new OutOfBoundsException(
                message: 'No active HTML id is set for key ' . $key . '; check isActiveHtmlIdSet() first.',
            );
        }

        return $this->activeHtmlIds[$key];
    }

    /**
     * @return array<int, string>
     */
    public function listActiveHtmlIds(): array
    {
        return $this->activeHtmlIds;
    }

    /**
     * Renders the template with the content file. Without an active id, the requested file (with its group) is the
     * active one.
     *
     * @throws NotFoundException if there is no content file, or the requested group or file title leaves the content
     *                           directory (`..`, absolute path, backslash)
     * @throws RuntimeException if the active navigation cannot be marked in the output
     */
    public function render(): string
    {
        $fullContentFilePath = $this->findContentFile();
        $this->replacements->addHtml(identifier: 'this', html: $fullContentFilePath);
        $templateFilePath = $this->templateDirectory . $this->templateName . '.html';
        if ($this->templateName === '' || !is_file(filename: $templateFilePath)) {
            $templateFilePath = $fullContentFilePath;
        }
        if ($this->activeHtmlIds === []) {
            $fileGroup = $this->settings->fileGroup;
            $this->setActiveHtmlId(
                key: 1,
                val: $fileGroup === null ? $this->settings->fileTitle : $fileGroup . '-' . $this->settings->fileTitle,
            );
        }
        $html = $this->templateEngine->render(
            templateFile: $templateFilePath,
            data: TemplateData::fromReplacements(replacements: $this->replacements),
        );

        return $this->markActiveNavigation(html: $html);
    }

    private function findContentFile(): string
    {
        $fileGroup = $this->settings->fileGroup;
        // The group and the title come from the request, the content file name is set by the view
        if (
            $this->contentFileName === ''
            || !HtmlDocument::isInsideDirectory(path: $this->settings->fileTitle)
            || ($fileGroup !== null && !HtmlDocument::isInsideDirectory(path: $fileGroup))
        ) {
            throw new NotFoundException();
        }
        $directory = $fileGroup === null ? $this->contentFileDirectory : $this->contentFileDirectory . $fileGroup . '/';
        $fullContentFilePath = $directory . $this->contentFileName;
        if (!is_file(filename: $fullContentFilePath)) {
            throw new NotFoundException();
        }

        return $fullContentFilePath;
    }

    /**
     * A path that comes from the request must stay below the directory it is appended to.
     */
    private static function isInsideDirectory(string $path): bool
    {
        return !str_contains(haystack: $path, needle: "\0")
            && !str_contains(haystack: $path, needle: '\\')
            && !str_starts_with(haystack: $path, needle: '/')
            && preg_match(pattern: '#(^|/)\.\.(/|$)#', subject: $path) === 0;
    }

    private function markActiveNavigation(string $html): string
    {
        if (!str_contains(haystack: $html, needle: 'id="nav-')) {
            return $html;
        }
        $activeIds = array_flip(array: array_values(array: $this->activeHtmlIds));
        $result = preg_replace_callback(
            pattern: '/(\s+id="nav-(.+?)")(\s+class="(.+?)")?/',
            callback: static function (array $matches) use ($activeIds): string {
                if (!array_key_exists(key: $matches[2], array: $activeIds)) {
                    // The id is not active, the match stays as it is
                    return $matches[0];
                }
                $classes = array_key_exists(key: 4, array: $matches) ? $matches[4] . ' ' : '';

                return $matches[1] . ' class="' . $classes . 'active"';
            },
            subject: $html,
        );
        if ($result === null) {
            throw new RuntimeException(
                message: 'The active navigation could not be marked: ' . preg_last_error_msg() . '.',
            );
        }

        return $result;
    }
}
