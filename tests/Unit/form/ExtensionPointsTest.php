<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\component\field\NullField;
use actra\yuf\form\component\field\OptionsField;
use actra\yuf\form\component\field\RadioOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\TextualField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormField;
use actra\yuf\form\component\FormInfo;
use actra\yuf\form\component\FormSubHeadline;
use actra\yuf\form\FormCollection;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\FormRule;
use actra\yuf\form\listener\FormFieldListener;
use actra\yuf\form\renderer\BooleanFieldListRenderer;
use actra\yuf\form\renderer\CheckboxItemRenderer;
use actra\yuf\form\renderer\CheckboxOptionsRenderer;
use actra\yuf\form\renderer\DefaultCollectionRenderer;
use actra\yuf\form\renderer\DefaultComponentRenderer;
use actra\yuf\form\renderer\DefaultFormRenderer;
use actra\yuf\form\renderer\DefaultOptionsRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\form\renderer\FileFieldRenderer;
use actra\yuf\form\renderer\FormControlRenderer;
use actra\yuf\form\renderer\FormInfoRenderer;
use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\renderer\InputFieldRenderer;
use actra\yuf\form\renderer\LegendAndListRenderer;
use actra\yuf\form\renderer\NumericFieldRenderer;
use actra\yuf\form\renderer\RadioOptionsRenderer;
use actra\yuf\form\renderer\SelectOptionsRenderer;
use actra\yuf\form\renderer\TextAreaRenderer;
use actra\yuf\form\renderer\ToggleFieldRenderer;
use actra\yuf\form\rule\StringRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Pins which classes of `src/form/` are extension points (projects extend them) and which are `final`.
 */
final class ExtensionPointsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function finalClassProvider(): iterable
    {
        foreach ([
            FileField::class, MultiSelectOptionsField::class, MultiToggleField::class, ToggleField::class,
            NullField::class, FormInfo::class, FormSubHeadline::class,
            BooleanFieldListRenderer::class, CheckboxItemRenderer::class, CheckboxOptionsRenderer::class,
            DefaultCollectionRenderer::class, DefaultComponentRenderer::class, DefaultFormRenderer::class,
            DefinitionListRenderer::class, FileFieldRenderer::class, FormControlRenderer::class,
            FormInfoRenderer::class, HiddenFieldRenderer::class, LegendAndListRenderer::class,
            NumericFieldRenderer::class, RadioOptionsRenderer::class, SelectOptionsRenderer::class,
            TextAreaRenderer::class, ToggleFieldRenderer::class,
        ] as $className) {
            yield $className => [$className];
        }
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('finalClassProvider')]
    public function testClassIsFinal(string $className): void
    {
        $this->assertTrue(new ReflectionClass(objectOrClass: $className)->isFinal());
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function extensionPointProvider(): iterable
    {
        foreach ([
            Form::class, TextField::class, TextAreaField::class, SelectOptionsField::class,
            CheckboxOptionsField::class, RadioOptionsField::class, BooleanField::class, IntegerField::class,
            FormControl::class, InputFieldRenderer::class,
        ] as $className) {
            yield $className => [$className];
        }
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('extensionPointProvider')]
    public function testExtensionPointIsNotFinalAndDocumented(string $className): void
    {
        $reflection = new ReflectionClass(objectOrClass: $className);

        $this->assertFalse($reflection->isFinal());
        $this->assertStringContainsString('Extension point', (string) $reflection->getDocComment());
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function abstractBaseProvider(): iterable
    {
        foreach ([
            FormComponent::class, FormField::class, FormCollection::class, FormRenderer::class, FormRule::class,
            StringRule::class, FormFieldListener::class, TextualField::class, OptionsField::class,
            DefaultOptionsRenderer::class,
        ] as $className) {
            yield $className => [$className];
        }
    }

    /**
     * @param class-string $className
     */
    #[DataProvider('abstractBaseProvider')]
    public function testBaseClassIsAbstract(string $className): void
    {
        $this->assertTrue(new ReflectionClass(objectOrClass: $className)->isAbstract());
    }
}
