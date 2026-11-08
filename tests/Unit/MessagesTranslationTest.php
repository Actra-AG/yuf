<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit;

use actra\yuf\auth\AuthResultMessages;
use actra\yuf\form\FormMessages;
use actra\yuf\table\TableMessages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The French and Italian texts of `FormMessages`, `TableMessages` and `AuthResultMessages`: every text translated (not
 * the English or German one), the same placeholders, formal address.
 */
final class MessagesTranslationTest extends TestCase
{
    /**
     * @return array<string, array{string, object, object, object}> Language and class, the translation, the English
     *     and the German texts
     */
    public static function translationsProvider(): array
    {
        $classes = [
            'FormMessages' => [
                FormMessages::french(),
                FormMessages::italian(),
                new FormMessages(),
                FormMessages::german(),
            ],
            'TableMessages' => [
                TableMessages::french(),
                TableMessages::italian(),
                TableMessages::english(),
                new TableMessages(),
            ],
            'AuthResultMessages' => [
                AuthResultMessages::french(),
                AuthResultMessages::italian(),
                AuthResultMessages::english(),
                new AuthResultMessages(),
            ],
        ];
        $translations = [];
        foreach ($classes as $class => [$french, $italian, $english, $german]) {
            $translations['fr ' . $class] = ['fr ' . $class, $french, $english, $german];
            $translations['it ' . $class] = ['it ' . $class, $italian, $english, $german];
        }

        return $translations;
    }

    #[DataProvider('translationsProvider')]
    public function testEveryTextIsTranslated(string $name, object $translation, object $english, object $german): void
    {
        $englishTexts = MessagesTranslationTest::textsOf(messages: $english);
        $germanTexts = MessagesTranslationTest::textsOf(messages: $german);
        $texts = MessagesTranslationTest::textsOf(messages: $translation);
        $untranslated = [];
        foreach ($texts as $textName => $text) {
            $sources = [$englishTexts[$textName] ?? null, $germanTexts[$textName] ?? null];
            if (trim(string: $text) === '' || in_array(needle: $text, haystack: $sources, strict: true)) {
                $untranslated[] = $textName;
            }
        }

        $this->assertSame(array_keys($englishTexts), array_keys($texts));
        $this->assertSame([], $untranslated);
    }

    #[DataProvider('translationsProvider')]
    public function testPlaceholdersAreTheSame(string $name, object $translation, object $english, object $german): void
    {
        $texts = MessagesTranslationTest::textsOf(messages: $translation);
        foreach (MessagesTranslationTest::textsOf(messages: $english) as $textName => $englishText) {
            $this->assertSame(
                MessagesTranslationTest::placeholdersOf(text: $englishText),
                MessagesTranslationTest::placeholdersOf(text: $texts[$textName] ?? ''),
                $name . ': placeholders in ' . $textName,
            );
        }
    }

    /**
     * Formal address as used in Switzerland: "vous", "Lei".
     */
    #[DataProvider('translationsProvider')]
    public function testFormalAddress(string $name, object $translation, object $english, object $german): void
    {
        $pattern = str_starts_with(haystack: $name, needle: 'fr ')
            ? '/\b(tu|te|toi|ton|ta|tes)\b/iu'
            : '/\b(tu|ti|tuo|tua|tuoi|tue)\b/iu';
        $informalTexts = [];
        foreach (MessagesTranslationTest::textsOf(messages: $translation) as $textName => $text) {
            if (preg_match(pattern: $pattern, subject: $text) === 1) {
                $informalTexts[] = $textName;
            }
        }

        $this->assertSame([], $informalTexts);
    }

    /**
     * @return array<string, string>
     */
    private static function textsOf(object $messages): array
    {
        $texts = [];
        foreach (get_object_vars($messages) as $name => $text) {
            if (is_string(value: $name) && is_string(value: $text)) {
                $texts[$name] = $text;
            }
        }

        return $texts;
    }

    /**
     * @return list<string>
     */
    private static function placeholdersOf(string $text): array
    {
        preg_match_all('/\[\w+]/', $text, $matches);
        $placeholders = $matches[0];
        sort($placeholders);

        return $placeholders;
    }
}
