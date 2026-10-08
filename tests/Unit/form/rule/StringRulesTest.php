<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\rule;

use actra\yuf\form\rule\MaxLengthRule;
use actra\yuf\form\rule\MinLengthRule;
use actra\yuf\form\rule\RegexRule;
use actra\yuf\form\rule\StringRule;
use actra\yuf\form\rule\ValidEmailAddressRule;
use actra\yuf\form\rule\ValidValueRule;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class StringRulesTest extends TestCase
{
    private function message(): HtmlText
    {
        return HtmlText::fromHtml(html: 'Error');
    }

    /**
     * @return iterable<string, array{StringRule, string, bool}>
     */
    public static function ruleProvider(): iterable
    {
        $message = HtmlText::fromHtml(html: 'Error');
        yield 'min length reached' => [new MinLengthRule(minLength: 3, errorMessage: $message), 'abc', true];
        yield 'min length not reached' => [new MinLengthRule(minLength: 3, errorMessage: $message), 'ab', false];
        yield 'min length counts characters' => [new MinLengthRule(minLength: 3, errorMessage: $message), 'äöü', true];
        yield 'max length reached' => [new MaxLengthRule(maxLength: 3, errorMessage: $message), 'abc', true];
        yield 'max length exceeded' => [new MaxLengthRule(maxLength: 3, errorMessage: $message), 'abcd', false];
        yield 'max length counts characters' => [new MaxLengthRule(maxLength: 3, errorMessage: $message), 'äöü', true];
        yield 'regex matches' => [new RegexRule(pattern: '/^\d+$/', errorMessage: $message), '123', true];
        yield 'regex does not match' => [new RegexRule(pattern: '/^\d+$/', errorMessage: $message), '12a', false];
        yield 'valid value' => [new ValidValueRule(validValues: ['a', 'b'], errorMessage: $message), 'b', true];
        yield 'invalid value' => [new ValidValueRule(validValues: ['a', 'b'], errorMessage: $message), 'c', false];
        yield 'valid value is compared exactly' => [
            new ValidValueRule(validValues: ['1'], errorMessage: $message),
            '01',
            false,
        ];
        yield 'email without dns check' => [
            new ValidEmailAddressRule(errorMessage: $message, dnsCheck: false),
            'a@example.com',
            true,
        ];
        yield 'email with invalid syntax' => [
            new ValidEmailAddressRule(errorMessage: $message, dnsCheck: false),
            'not an address',
            false,
        ];
    }

    #[DataProvider('ruleProvider')]
    public function testRuleValidatesTheText(StringRule $rule, string $value, bool $expected): void
    {
        $this->assertSame($expected, $rule->validate(value: $value));
    }

    public function testRuleKeepsItsErrorMessage(): void
    {
        $rule = new MinLengthRule(minLength: 3, errorMessage: $this->message());

        $this->assertSame('Error', $rule->getErrorMessage()->render());
    }

    public function testErrorMessageCanBeReplaced(): void
    {
        $rule = new MinLengthRule(minLength: 3, errorMessage: $this->message());

        $rule->setErrorMessage(errorMessage: HtmlText::fromHtml(html: 'Other'));

        $this->assertSame('Other', $rule->getErrorMessage()->render());
    }

    public function testRulesAreNotFinal(): void
    {
        foreach ([MinLengthRule::class, MaxLengthRule::class, RegexRule::class, ValidValueRule::class,
            ValidEmailAddressRule::class] as $className) {
            $this->assertFalse(new ReflectionClass(objectOrClass: $className)->isFinal(), $className);
        }
    }
}
