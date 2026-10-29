<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The capabilities that the server announced in an untagged `CAPABILITY` response.
 *
 * @internal
 */
final readonly class ImapCapabilities
{
    /**
     * @param list<string> $names upper case, also those that yuf does not use
     */
    private function __construct(public array $names) {}

    public static function fromResponse(ImapResponse $response): ImapCapabilities
    {
        $names = [];
        foreach ($response->findUntagged(type: 'CAPABILITY') as $line) {
            $words = preg_split(
                pattern: '/\s+/',
                subject: strtoupper(string: $line->getText()),
                flags: PREG_SPLIT_NO_EMPTY,
            );
            foreach (array_slice(array: $words === false ? [] : $words, offset: 2) as $word) {
                $names[] = $word;
            }
        }

        return new ImapCapabilities(names: array_values(array: array_unique(array: $names)));
    }

    public function has(ImapCapabilityEnum $capability): bool
    {
        return in_array(needle: $capability->value, haystack: $this->names, strict: true);
    }
}
