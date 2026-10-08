<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\layout\CheckboxOptionsLayoutEnum;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the markup of `BooleanField` in a form (required, with field info) to the HTML of v3.3.1, where the field was
 * a `CheckboxOptionsField`. The unchecked variants have been validated, so they show the error.
 */
final class BooleanFieldV3MarkupTest extends TestCase
{
    private static int $formCounter = 0;

    /**
     * @return iterable<string, array{CheckboxOptionsLayoutEnum, bool, string}>
     */
    public static function markupProvider(): iterable
    {
        yield 'definition list checked' => [
            CheckboxOptionsLayoutEnum::DEFINITION_LIST,
            true,
            '<form method="post" action="?%s"><dl><dt><label for="bo">L<span class="required">*</span>'
            . '</label></dt><dd><ul><li class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo_checked" value="checked" checked>'
            . '<label class="form-check-label" for="bo_checked">L</label></li></ul>'
            . '<div class="form-input-info" id="bo-info">Info</div></dd></dl></form>',
        ];
        yield 'definition list unchecked' => [
            CheckboxOptionsLayoutEnum::DEFINITION_LIST,
            false,
            '<form method="post" action="?%s"><dl><dt><label for="bo">L<span class="required">*</span>'
            . '</label></dt><dd class="has-error"><ul class="list-has-error"><li class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo_checked" value="checked">'
            . '<label class="form-check-label" for="bo_checked">L</label></li></ul>'
            . '<div class="form-input-error" id="bo-error" role="alert" aria-live="assertive">Req</div>'
            . '<div class="form-input-info" id="bo-info">Info</div></dd></dl></form>',
        ];
        yield 'none checked' => [
            CheckboxOptionsLayoutEnum::NONE,
            true,
            '<form method="post" action="?%s"><dl><dt><label for="bo">L<span class="required">*</span>'
            . '</label></dt><dd><ul><li class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo_checked" value="checked" checked>'
            . '<label class="form-check-label" for="bo_checked">L</label></li></ul>'
            . '<div class="form-input-info" id="bo-info">Info</div></dd></dl></form>',
        ];
        yield 'none unchecked' => [
            CheckboxOptionsLayoutEnum::NONE,
            false,
            '<form method="post" action="?%s"><dl><dt><label for="bo">L<span class="required">*</span>'
            . '</label></dt><dd class="has-error"><ul class="list-has-error"><li class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo_checked" value="checked">'
            . '<label class="form-check-label" for="bo_checked">L</label></li></ul>'
            . '<div class="form-input-error" id="bo-error" role="alert" aria-live="assertive">Req</div>'
            . '<div class="form-input-info" id="bo-info">Info</div></dd></dl></form>',
        ];
        yield 'legend and list checked' => [
            CheckboxOptionsLayoutEnum::LEGEND_AND_LIST,
            true,
            '<form method="post" action="?%s">'
            . '<fieldset class="legend-and-list" aria-describedby="bo-info">'
            . '<legend>L<span class="required">*</span></legend><ul><li class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo_checked" value="checked" checked>'
            . '<label class="form-check-label" for="bo_checked">L</label></li></ul>'
            . '<div class="form-input-info" id="bo-info">Info</div></fieldset></form>',
        ];
        yield 'legend and list unchecked' => [
            CheckboxOptionsLayoutEnum::LEGEND_AND_LIST,
            false,
            '<form method="post" action="?%s">'
            . '<fieldset class="legend-and-list" aria-invalid="true" aria-describedby="bo-error bo-info">'
            . '<legend>L<span class="required">*</span></legend><ul class="list-has-error">'
            . '<li class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo_checked" value="checked">'
            . '<label class="form-check-label" for="bo_checked">L</label></li></ul>'
            . '<div class="form-input-error" id="bo-error" role="alert" aria-live="assertive">Req</div>'
            . '<div class="form-input-info" id="bo-info">Info</div></fieldset></form>',
        ];
        yield 'checkbox item checked' => [
            CheckboxOptionsLayoutEnum::CHECKBOX_ITEM,
            true,
            '<form method="post" action="?%s"><div class="form-check">'
            . '<input type="checkbox" name="bo[]" id="bo" value="checked" checked aria-describedby="bo-info">'
            . '<label for="bo" class="form-check-label">L</label>'
            . '<div class="form-input-info" id="bo-info">Info</div></div></form>',
        ];
        yield 'checkbox item unchecked' => [
            CheckboxOptionsLayoutEnum::CHECKBOX_ITEM,
            false,
            '<form method="post" action="?%s"><div class="form-check has-error">'
            . '<input type="checkbox" name="bo[]" id="bo" value="checked" aria-invalid="true"'
            . ' aria-describedby="bo-error bo-info">'
            . '<label for="bo" class="form-check-label">L</label>'
            . '<div class="form-input-info" id="bo-info">Info</div>'
            . '<div class="form-input-error" id="bo-error" role="alert" aria-live="assertive">Req</div>'
            . '</div></form>',
        ];
    }

    #[DataProvider('markupProvider')]
    public function testFormMarkupIsTheMarkupOfV3(
        CheckboxOptionsLayoutEnum $layout,
        bool $checked,
        string $expected,
    ): void {
        $formName = 'booleanMarkupForm' . BooleanFieldV3MarkupTest::$formCounter++;
        $form = new Form(context: FormContextFactory::create(), name: $formName);
        $field = new BooleanField(
            name: 'bo',
            label: HtmlText::fromHtml(html: 'L'),
            isCheckedByDefault: $checked,
            requiredError: HtmlText::fromHtml(html: 'Req'),
            layout: $layout,
        );
        $field->fieldInfo = HtmlText::fromHtml(html: 'Info');
        $form->addField(formField: $field);
        if (!$checked) {
            $field->validate(input: FormInput::fromArray(data: []));
        }

        $html = preg_replace(
            pattern: '#<input type="hidden" name="csrftoken"[^>]*>#',
            replacement: '',
            subject: $form->render(),
        );

        $this->assertSame(sprintf($expected, $formName), $html);
    }
}
