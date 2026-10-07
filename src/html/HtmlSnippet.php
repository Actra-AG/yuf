<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use actra\yuf\Core;
use actra\yuf\core\RequestHandler;
use actra\yuf\security\CspNonce;
use actra\yuf\template\template\DirectoryTemplateCache;
use actra\yuf\template\template\TemplateEngine;

readonly class HtmlSnippet
{
    public function __construct(
        private string $htmlSnippetFilePath,
        public HtmlReplacementCollection $replacements = new HtmlReplacementCollection(),
        private ?CspNonce $cspNonce = null,
    ) {}

    public static function createForCurrentView(string $snippetName, ?CspNonce $cspNonce = null): HtmlSnippet
    {
        return new HtmlSnippet(
            htmlSnippetFilePath: RequestHandler::get(
            )->route->viewDirectory . 'snippets' . DIRECTORY_SEPARATOR . $snippetName . '.html',
            cspNonce: $cspNonce,
        );
    }

    public function render(): string
    {
        $htmlSnippetFilePath = $this->htmlSnippetFilePath;
        $replacements = $this->replacements;
        if (
            $this->cspNonce !== null
            && !$replacements->has(identifier: 'cspNonce')
        ) {
            $replacements->addEncodedText(identifier: 'cspNonce', content: $this->cspNonce->value);
        }
        $core = Core::get();
        return new TemplateEngine(
            templateCacheInterface: new DirectoryTemplateCache(
                cachePath: $core->cacheDirectory,
                templateBaseDirectory: $core->baseDirectory,
            ),
            tplNsPrefix: 'tst',
        )->getResultAsHtml(
            tplFile: $htmlSnippetFilePath,
            dataPool: $this->replacements->getArrayObject(),
        );
    }
}
