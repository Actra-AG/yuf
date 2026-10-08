<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Error;
use Exception;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1). The renderer
 * gives the engine a LocaleHandler without language and the texts of tests/Fixture/template/lang.lang.php.
 */
abstract class AbstractTemplateLangTagTestCase extends TemplateCharacterizationTestCase
{
    public function testInlineTagOutputsText(): void
    {
        $this->assertSame('[Plain text]', $this->renderSource(source: "[{tst:lang key='plain'}]"));
    }

    public function testElementTagOutputsText(): void
    {
        $this->assertSame('[Plain text]', $this->renderSource(source: '[<tst:lang key="plain"/>]'));
    }

    public function testPlaceholdersStayWithoutVars(): void
    {
        $html = $this->renderSource(source: "{tst:lang key='greeting'}");

        $this->assertSame('Hello [NAME], welcome to [place]', $html);
    }

    public function testTextIsNotEscaped(): void
    {
        $html = $this->renderSource(source: "{tst:lang key='markup'}");

        $this->assertSame('Fish & <b>chips</b>', $html);
    }

    public function testMissingKeyThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:lang key='nokey'}");

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Missing language fragment for nokey',
            newMessage: 'Missing language fragment "nokey" in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testVarsAttribute(): void
    {
        // Differs: the old engine fails with "Class LangTag not found", in the new engine vars is the selector of an
        // array of values (see LangTagTest of the new engine); a string is rejected
        $templateFile = $this->writeTemplate(source: "{tst:lang key='greeting' vars='name'}");

        $this->expectEngineException(
            oldClass: Error::class,
            oldMessage: 'Class "LangTag" not found',
            newMessage: 'The vars "name" must be an array with the values for the placeholders, got string in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['name' => 'Anna']);
    }

    public function testVarsAttributeOfTheElementForm(): void
    {
        // Differs: the old engine fails with "Class LangTag not found", in the new engine vars is the selector of an
        // array of values (see LangTagTest of the new engine); a string is rejected
        $templateFile = $this->writeTemplate(source: '<tst:lang key="greeting" vars="name"/>');

        $this->expectEngineException(
            oldClass: Error::class,
            oldMessage: 'Class "LangTag" not found',
            newMessage: 'The vars "name" must be an array with the values for the placeholders, got string in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['name' => 'Anna']);
    }
}
