<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\tag;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;

final class LangTagTest extends TemplateEngineTestCase
{
    public function testTextIsOutputAsItIs(): void
    {
        $this->assertSame('[Fish & <b>chips</b>]', $this->render(source: "[{tst:lang key='markup'}]"));
        $this->assertSame('[Fish & <b>chips</b>]', $this->render(source: '[<tst:lang key="markup"/>]'));
    }

    public function testPlaceholdersStayWithoutVars(): void
    {
        $this->assertSame('Hello [NAME], welcome to [place]', $this->render(source: "{tst:lang key='greeting'}"));
    }

    public function testVarsReplaceThePlaceholders(): void
    {
        $html = $this->render(
            source: "{tst:lang key='greeting' vars='texts'}",
            data: ['texts' => ['name' => 'Anna', 'place' => 'Bern']],
        );

        $this->assertSame('Hello Anna, welcome to Bern', $html);
    }

    public function testVarsOfTheElementForm(): void
    {
        $html = $this->render(
            source: '<tst:lang key="greeting" vars="texts"/>',
            data: ['texts' => ['NAME' => 'Anna']],
        );

        $this->assertSame('Hello Anna, welcome to [place]', $html);
    }

    public function testVarsAreEscaped(): void
    {
        $html = $this->render(
            source: "{tst:lang key='greeting' vars='texts'}",
            data: ['texts' => ['name' => '<b>"Anna"</b>', 'place' => "O'Neil & Co"]],
        );

        $this->assertSame('Hello &lt;b&gt;&quot;Anna&quot;&lt;/b&gt;, welcome to O&#039;Neil &amp; Co', $html);
    }

    public function testVarsOfAnObject(): void
    {
        $html = $this->render(
            source: "{tst:lang key='greeting' vars='page.texts'}",
            data: ['page' => (object) ['texts' => ['name' => 'Anna']]],
        );

        $this->assertSame('Hello Anna, welcome to [place]', $html);
    }

    public function testVarsThatAreNotAnArrayThrow(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:lang key='greeting' vars='name'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The vars "name" must be an array with the values for the placeholders, got string in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['name' => 'Anna']);
    }

    public function testVarsWithAnArrayAsValueThrow(): void
    {
        $templateFile = $this->writeTemplate(source: "{tst:lang key='greeting' vars='texts'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Cannot output a value of type array, only text, numbers, booleans and null in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['texts' => ['name' => ['Anna']]]);
    }

    public function testMissingKeyThrows(): void
    {
        $templateFile = $this->writeTemplate(source: "a\n{tst:lang key='nokey'}");

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing language fragment "nokey" in ' . $templateFile . ' on line 2');

        $this->renderFile(templateFile: $templateFile);
    }

    public function testMissingKeyAttributeThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:lang/>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('Missing attribute "key" in ' . $templateFile . ' on line 1');

        $this->renderFile(templateFile: $templateFile);
    }
}
