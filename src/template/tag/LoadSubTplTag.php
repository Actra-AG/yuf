<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\tag;

use Closure;
use Override;

/**
 * `<tst:loadSubTpl tplfile="path"/>`: renders another template with the same data. `tplfile="{key}"` takes the path
 * from the template data.
 */
final readonly class LoadSubTplTag implements TemplateTag
{
    #[Override]
    public function getName(): string
    {
        return 'loadSubTpl';
    }

    #[Override]
    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $templateFile = $context->requireAttribute(attributes: $attributes, name: 'tplfile');
        if (preg_match(pattern: '/^\{(.+)\}$/', subject: $templateFile, matches: $matches) === 1) {
            $templateFile = $context->text(value: $context->resolve(selector: $matches[1]));
        }
        if ($templateFile === '') {
            return '';
        }

        return $context->renderTemplate(templateFile: $templateFile);
    }
}
