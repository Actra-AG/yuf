<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Error;
use Exception;
use Override;
use ReflectionClass;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1).
 *
 * The lang tag reads the texts through the static LocaleHandler::get(). The test registers a handler without language
 * (so register() does not call setlocale()) and resets the private static instance by reflection afterwards, as there
 * is no public way to unregister it.
 */
final class TemplateLangTagTest extends TemplateCharacterizationTestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        TemplateLangTagTest::resetRegisteredLocaleHandler();
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());
        $localeHandler->loadLanguageFile(filePath: TemplateLangTagTest::fixtureDirectory() . 'lang.lang.php');
        LocaleHandler::register(localeHandler: $localeHandler);
    }

    #[Override]
    protected function tearDown(): void
    {
        TemplateLangTagTest::resetRegisteredLocaleHandler();
        parent::tearDown();
    }

    private static function resetRegisteredLocaleHandler(): void
    {
        new ReflectionClass(objectOrClass: LocaleHandler::class)->setStaticPropertyValue(
            name: 'registeredInstance',
            value: null,
        );
    }

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
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('Missing language fragment for nokey');

        $this->renderSource(source: "{tst:lang key='nokey'}");
    }

    /**
     * The vars attribute is broken: the compiled code calls the missing static method LangTag::getData() in the
     * wrong namespace. Works again in the rewrite (design section 3).
     */
    public function testVarsAttributeIsBroken(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessageIs('Class "LangTag" not found');

        $this->renderSource(source: "{tst:lang key='greeting' vars='name'}", data: ['name' => 'Anna']);
    }

    public function testVarsAttributeOfTheElementFormIsBroken(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessageIs('Class "LangTag" not found');

        $this->renderSource(source: '<tst:lang key="greeting" vars="name"/>', data: ['name' => 'Anna']);
    }
}
