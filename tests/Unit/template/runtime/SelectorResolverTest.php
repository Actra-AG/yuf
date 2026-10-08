<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\runtime;

use actra\yuf\template\runtime\SelectorResolver;
use actra\yuf\template\runtime\TemplateScopes;
use actra\yuf\template\runtime\TrustedHtml;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\KeyedObject;
use actra\yuf\tests\Double\template\SelectorProbe;
use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SelectorResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private function resolve(string $selector, array $values): mixed
    {
        return new SelectorResolver()->resolve(selector: $selector, scopes: new TemplateScopes(data: new TemplateData(values: $values)));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function selectorProvider(): iterable
    {
        yield 'array key' => ['a.b', 'array'];
        yield 'nested array keys' => ['n.x.y', 'deep'];
        yield 'numeric array key' => ['list.1', 'second'];
        yield 'ArrayObject key' => ['ao.k', 'ao value'];
        yield 'stdClass property' => ['std.name', 'std name'];
        yield 'public property' => ['probe.name', 'property'];
        yield 'getter get' => ['probe.caption', 'get caption'];
        yield 'public method without arguments' => ['probe.summary', 'method hidden'];
        yield 'getter wins over a method of the same name' => ['probe.both', 'property'];
        yield 'array inside an object' => ['std.list.0', 'first'];
        yield 'null value' => ['nothing', null];
    }

    #[DataProvider('selectorProvider')]
    public function testSelector(string $selector, mixed $expected): void
    {
        $std = new stdClass();
        $std->name = 'std name';
        $std->list = ['first'];

        $value = $this->resolve(selector: $selector, values: [
            'a' => ['b' => 'array'],
            'n' => ['x' => ['y' => 'deep']],
            'list' => ['first', 'second'],
            'ao' => new ArrayObject(array: ['k' => 'ao value']),
            'std' => $std,
            'probe' => new SelectorProbe(),
            'nothing' => null,
        ]);

        $this->assertSame($expected, $value);
    }

    public function testArrayKeyWinsOverPropertyOfAnArrayAccessObject(): void
    {
        $this->assertSame('key', $this->resolve(selector: 'o.name', values: ['o' => new KeyedObject()]));
    }

    public function testPropertyWinsOverGetter(): void
    {
        $this->assertSame('property', $this->resolve(selector: 'p.both', values: ['p' => new SelectorProbe()]));
    }

    public function testGetterPrefixesAreTriedInTheOrderGetIsHas(): void
    {
        $probe = new SelectorProbe();

        $this->assertSame('get caption', $this->resolve(selector: 'p.caption', values: ['p' => $probe]));
        $this->assertTrue($this->resolve(selector: 'p.items', values: ['p' => $probe]));
    }

    public function testResolvesTheSameValueAsItsOwnScopeEntry(): void
    {
        $scopes = new TemplateScopes(data: new TemplateData(values: ['i' => ['v' => 'outer']]));
        $scopes->push(name: 'i', value: ['v' => 'inner']);

        $this->assertSame('inner', new SelectorResolver()->resolve(selector: 'i.v', scopes: $scopes));
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function failingSelectorProvider(): iterable
    {
        $cannotRead = static fn(string $name, string $path): string => 'Cannot read "' . $name . '" of "' . $path
            . '": no key, public property, getter or method without arguments of this name';

        yield 'missing top-level value' => [
            'x',
            null,
            'The template data "x" does not exist. Check that the view provides a replacement with this identifier',
        ];
        yield 'missing array key' => ['arr.z', null, 'The array "arr" has no key "z"'];
        yield 'missing key of a nested array' => ['arr.n.z', null, 'The array "arr.n" has no key "z"'];
        yield 'missing ArrayObject key' => ['ao.z', null, $cannotRead('z', 'ao')];
        yield 'missing property' => ['std.z', null, $cannotRead('z', 'std')];
        yield 'private property' => ['probe.hidden', null, $cannotRead('hidden', 'probe')];
        yield 'protected method' => ['probe.protectedMethod', null, $cannotRead('protectedMethod', 'probe')];
        yield 'static method' => ['probe.staticMethod', null, $cannotRead('staticMethod', 'probe')];
        yield 'method with a required argument' => ['probe.needsArgument', null, $cannotRead('needsArgument', 'probe')];
        yield 'method call syntax' => ['probe.needsArgument(x)', null, $cannotRead('needsArgument(x)', 'probe')];
        yield 'method call syntax without arguments' => ['probe.summary()', null, $cannotRead('summary()', 'probe')];
        yield 'part of a string' => ['text.y', null, 'Cannot read "y" of "text": the value is not an array and not an object'];
        yield 'part of null' => ['nothing.y', null, 'Cannot read "y" of "nothing": the value is not an array and not an object'];
        yield 'part of trusted HTML' => ['html.y', null, 'Cannot read "y" of "html": the value is not an array and not an object'];
        yield 'empty part' => ['arr.', null, 'The array "arr" has no key ""'];
    }

    #[DataProvider('failingSelectorProvider')]
    public function testUnresolvableSelectorThrows(string $selector, mixed $unused, string $expectedReason): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs($expectedReason);

        $this->resolve(selector: $selector, values: [
            'arr' => ['a' => 1, 'n' => ['m' => 1]],
            'ao' => new ArrayObject(array: ['k' => 1]),
            'std' => new stdClass(),
            'probe' => new SelectorProbe(),
            'text' => 'abc',
            'nothing' => null,
            'html' => new TrustedHtml(html: '<b>'),
        ]);
    }

    public function testResolvingDoesNotChangeTheData(): void
    {
        $data = new TemplateData(values: ['a' => ['b' => 1]]);
        $scopes = new TemplateScopes(data: $data);

        new SelectorResolver()->resolve(selector: 'a.b', scopes: $scopes);

        $this->assertSame(['a' => ['b' => 1]], $data->values);
    }
}
