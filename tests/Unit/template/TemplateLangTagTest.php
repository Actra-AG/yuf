<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;

/**
 * The `lang` tag. The renderer gives the engine a LocaleHandler without language and the texts of
 * tests/Fixture/template/lang.lang.php.
 */
final class TemplateLangTagTest extends TemplateEngineTestCase
{
    public function testInlineTagOutputsText(): void
    {
        $this->assertSame('[Plain text]', $this->render(source: "[{tst:lang key='plain'}]"));
    }

    public function testElementTagOutputsText(): void
    {
        $this->assertSame('[Plain text]', $this->render(source: '[<tst:lang key="plain"/>]'));
    }

    public function testPlaceholdersStayWithoutVars(): void
    {
        $html = $this->render(source: "{tst:lang key='greeting'}");

        $this->assertSame('Hello [NAME], welcome to [place]', $html);
    }

    public function testTextIsNotEscaped(): void
    {
        $html = $this->render(source: "{tst:lang key='markup'}");

        $this->assertSame('Fish & <b>chips</b>', $html);
    }

    public function testMissingKeyThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:lang key='nokey'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing language fragment "nokey" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }

    public function testVarsAttribute(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:lang key='greeting' vars='name'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The vars "name" must be an array with the values for the placeholders, got string in ' . $templateFile
                . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['name' => 'Anna']);
    }

    public function testVarsAttributeOfTheElementForm(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:lang key="greeting" vars="name"/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The vars "name" must be an array with the values for the placeholders, got string in ' . $templateFile
                . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['name' => 'Anna']);
    }
}
