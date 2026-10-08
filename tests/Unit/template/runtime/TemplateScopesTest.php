<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\runtime;

use actra\yuf\template\runtime\TemplateScopes;
use actra\yuf\template\TemplateData;
use PHPUnit\Framework\TestCase;

final class TemplateScopesTest extends TestCase
{
    public function testValuesOfTheDataAreFound(): void
    {
        $scopes = new TemplateScopes(data: new TemplateData(values: ['a' => 1, 'n' => null]));

        $this->assertTrue($scopes->has(name: 'a'));
        $this->assertSame(1, $scopes->get(name: 'a'));
        $this->assertTrue($scopes->has(name: 'n'));
        $this->assertNull($scopes->get(name: 'n'));
        $this->assertFalse($scopes->has(name: 'missing'));
    }

    public function testPushedScopeShadowsAndPopRestoresTheOuterValue(): void
    {
        $scopes = new TemplateScopes(data: new TemplateData(values: ['i' => 'outer']));

        $scopes->push(name: 'i', value: 'inner');
        $this->assertSame('inner', $scopes->get(name: 'i'));
        $scopes->pop();

        $this->assertSame('outer', $scopes->get(name: 'i'));
    }

    public function testPushedScopeIsGoneAfterPop(): void
    {
        $scopes = new TemplateScopes(data: new TemplateData());

        $scopes->push(name: 'i', value: 1);
        $scopes->pop();

        $this->assertFalse($scopes->has(name: 'i'));
    }

    public function testNestedScopes(): void
    {
        $scopes = new TemplateScopes(data: new TemplateData(values: ['a' => 'data']));

        $scopes->push(name: 'x', value: 1);
        $scopes->push(name: 'x', value: 2);
        $this->assertSame(2, $scopes->get(name: 'x'));
        $this->assertSame('data', $scopes->get(name: 'a'));
        $scopes->pop();

        $this->assertSame(1, $scopes->get(name: 'x'));
    }

    public function testPopNeverRemovesTheData(): void
    {
        $scopes = new TemplateScopes(data: new TemplateData(values: ['a' => 1]));

        $scopes->pop();

        $this->assertSame(1, $scopes->get(name: 'a'));
    }
}
