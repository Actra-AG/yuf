<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Exception;
use stdClass;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1).
 */
abstract class AbstractTemplateForTagTestCase extends TemplateCharacterizationTestCase
{
    private const string LIST = '<tst:for value="l" var="i">[{tst:text value=\'i\'}]</tst:for>';

    public function testSimpleList(): void
    {
        $html = $this->renderSource(source: AbstractTemplateForTagTestCase::LIST, data: ['l' => [1, 2, 3]]);

        $this->assertSame('[1][2][3]', $html);
    }

    public function testSurroundingTextIsKept(): void
    {
        $html = $this->renderSource(source: 'a' . AbstractTemplateForTagTestCase::LIST . 'b', data: ['l' => ['x']]);

        $this->assertSame('a[x]b', $html);
    }

    public function testEmptyListRendersNothing(): void
    {
        $this->assertSame('ab', $this->renderSource(source: 'a' . AbstractTemplateForTagTestCase::LIST . 'b', data: ['l' => []]));
    }

    public function testNullListRendersNothing(): void
    {
        $this->assertSame('ab', $this->renderSource(source: 'a' . AbstractTemplateForTagTestCase::LIST . 'b', data: ['l' => null]));
    }

    public function testKeysOfAnAssociativeArrayAreIgnored(): void
    {
        $html = $this->renderSource(source: AbstractTemplateForTagTestCase::LIST, data: ['l' => ['a' => 'x', 'b' => 'y']]);

        $this->assertSame('[x][y]', $html);
    }

    public function testObjectPropertiesAreIterated(): void
    {
        $html = $this->renderSource(source: AbstractTemplateForTagTestCase::LIST, data: ['l' => (object) ['a' => 'x', 'b' => 'y']]);

        $this->assertSame('[x][y]', $html);
    }

    public function testListOfStdClassItems(): void
    {
        $first = new stdClass();
        $first->name = 'one';
        $first->count = 1;
        $second = new stdClass();
        $second->name = 'two';
        $second->count = 2;

        $html = $this->renderSource(
            source: '<tst:for value="l" var="item"><tst:text value="item.name"/>=<tst:text value="item.count"/>;</tst:for>',
            data: ['l' => [$first, $second]],
        );

        $this->assertSame('one=1;two=2;', $html);
    }

    public function testListOfArrays(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="item">{tst:text value=\'item.name\'},</tst:for>',
            data: ['l' => [['name' => 'a'], ['name' => 'b']]],
        );

        $this->assertSame('a,b,', $html);
    }

    public function testValueIsADottedSelector(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="o.list" var="i">{tst:text value=\'i\'}</tst:for>',
            data: ['o' => ['list' => [1, 2]]],
        );

        $this->assertSame('12', $html);
    }

    public function testDataOutsideTheLoopIsVisibleInside(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i">{tst:text value=\'o\'}</tst:for>',
            data: ['l' => [1, 2], 'o' => 'O'],
        );

        $this->assertSame('OO', $html);
    }

    public function testNestedLoops(): void
    {
        $source = '<tst:for value="mainNavigationItems" var="mainNavigationItem">'
            . '(<tst:text value="mainNavigationItem.name"/>:'
            . '<tst:for value="mainNavigationItem.subNavigation" var="subNavigationItem">'
            . '{tst:text value=\'subNavigationItem.name\'},'
            . '</tst:for>)'
            . '</tst:for>';
        $data = [
            'mainNavigationItems' => [
                ['name' => 'A', 'subNavigation' => [['name' => 'a1'], ['name' => 'a2']]],
                ['name' => 'B', 'subNavigation' => []],
                ['name' => 'C', 'subNavigation' => [['name' => 'c1']]],
            ],
        ];

        $this->assertSame('(A:a1,a2,)(B:)(C:c1,)', $this->renderSource(source: $source, data: $data));
    }

    public function testNestedLoopOverTheOuterItem(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="row"><tst:for value="row" var="cell">{tst:text value=\'cell\'}</tst:for>;</tst:for>',
            data: ['l' => [[1, 2], [3]]],
        );

        $this->assertSame('12;3;', $html);
    }

    public function testNestedLoopMayReuseTheVariableName(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i"><tst:for value="i" var="i">{tst:text value=\'i\'}</tst:for>;</tst:for>',
            data: ['l' => [[1, 2], [3]]],
        );

        $this->assertSame('12;3;', $html);
    }

    public function testIfInsideLoop(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i"><tst:if compare="i" operator="gt" against="1">big </tst:if><tst:else>small </tst:else></tst:for>',
            data: ['l' => [1, 2]],
        );

        $this->assertSame('small big ', $html);
    }

    public function testLoopVariableShadowingAnOuterValue(): void
    {
        // Differs: the old engine removes the outer value after the loop, the new engine keeps it
        $templateFile = $this->writeTemplate(source: AbstractTemplateForTagTestCase::LIST . '{tst:text value=\'i\'}');
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionCode(1);
            $this->expectExceptionMessageIs(
                'The data with offset "i" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
            );
        }

        $html = $this->renderFile(templateFile: $templateFile, data: ['l' => [1, 2], 'i' => 'outer']);

        $this->assertSame('[1][2]outer', $html);
    }

    public function testOuterValueIsShadowedInsideTheLoop(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i">{tst:text value=\'i\'}</tst:for>',
            data: ['l' => [1, 2], 'i' => 'outer'],
        );

        $this->assertSame('12', $html);
    }

    public function testBracedWordsInsideTheLoop(): void
    {
        // Differs: the old engine rewrites {var.prop} inside a for tag to an unescaped echo, the new engine keeps it as text
        $item = new stdClass();
        $item->html = '<b>raw</b>';

        $html = $this->renderSource(
            source: '<tst:for value="l" var="i">[{i.html}]</tst:for>',
            data: ['l' => [$item]],
        );

        $this->assertSame($this->forEngine(old: '[<b>raw</b>]', new: '[{i.html}]'), $html);
    }

    public function testBracesWithoutWordInsideTheLoopAreKept(): void
    {
        $html = $this->renderSource(
            source: '<tst:for value="l" var="i">function(){return 1}</tst:for>',
            data: ['l' => [1]],
        );

        $this->assertSame('function(){return 1}', $html);
    }

    public function testWhitespaceInsideTheLoopIsKept(): void
    {
        $source = "<ul>\n<tst:for value=\"l\" var=\"i\">\n  <li>{tst:text value='i'}</li>\n</tst:for>\n</ul>\n";

        // The line break right after the opening and after the closing tag is swallowed by the PHP closing tag of the
        // compiled code
        $this->assertSame("<ul>\n  <li>1</li>\n  <li>2</li>\n</ul>\n", $this->renderSource(source: $source, data: ['l' => [1, 2]]));
    }
}
