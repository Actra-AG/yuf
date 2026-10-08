<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

use actra\yuf\template\cache\TemplateCache;
use actra\yuf\template\compiler\TemplateCompiler;
use actra\yuf\template\parser\TemplateParser;
use actra\yuf\template\TemplateException;

/**
 * Finds the compiled PHP file of a template: from the cache, or by parsing and compiling the template.
 *
 * @internal
 */
final readonly class TemplateLoader
{
    public function __construct(
        private TemplateCache $cache,
        private TemplateParser $parser,
        private TemplateCompiler $compiler,
    ) {}

    /**
     * @throws TemplateException if the template does not exist or has a syntax error
     */
    public function getCompiledFile(string $templateFile): string
    {
        if (!is_file(filename: $templateFile)) {
            throw new TemplateException(reason: 'Template file not found: ' . $templateFile);
        }
        $compiledFile = $this->cache->find(templateFile: $templateFile);
        if ($compiledFile !== null) {
            return $compiledFile;
        }
        $source = file_get_contents(filename: $templateFile);
        if ($source === false) {
            throw new TemplateException(reason: 'Could not read the template file: ' . $templateFile);
        }

        return $this->cache->store(
            templateFile: $templateFile,
            compiledCode: $this->compiler->compile(
                nodes: $this->parser->parse(source: $source, templateFile: $templateFile),
                templateFile: $templateFile,
            ),
        );
    }
}
