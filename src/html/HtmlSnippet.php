<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\core\Route;
use actra\yuf\security\CspNonce;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateEngine;

readonly class HtmlSnippet
{
    public function __construct(
        private string $htmlSnippetFilePath,
        public HtmlReplacementCollection $replacements = new HtmlReplacementCollection(),
        private ?CspNonce $cspNonce = null,
    ) {}

    public static function createForCurrentView(
        Route $route,
        string $snippetName,
        ?CspNonce $cspNonce = null,
    ): HtmlSnippet {
        return new HtmlSnippet(
            htmlSnippetFilePath: $route->viewDirectory . 'snippets' . DIRECTORY_SEPARATOR . $snippetName . '.html',
            cspNonce: $cspNonce,
        );
    }

    public function render(TemplateEngine $templateEngine): string
    {
        $htmlSnippetFilePath = $this->htmlSnippetFilePath;
        $replacements = $this->replacements;
        if (
            $this->cspNonce !== null
            && !$replacements->has(identifier: 'cspNonce')
        ) {
            $replacements->addHtml(identifier: 'cspNonce', html: $this->cspNonce->value);
        }

        return $templateEngine->render(
            templateFile: $htmlSnippetFilePath,
            data: TemplateData::fromReplacements(replacements: $replacements),
        );
    }
}
