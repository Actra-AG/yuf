<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The answer to a command: the untagged responses and the tagged status line.
 *
 * @internal
 */
final readonly class ImapResponse
{
    /**
     * @param list<ImapResponseLine> $untaggedLines
     * @param string                 $statusLine    the tagged line, e.g. `A0003 OK Select completed.`
     */
    public function __construct(
        public array $untaggedLines,
        public ImapResponseStatusEnum $status,
        public string $statusLine,
    ) {}

    /**
     * @return list<ImapResponseLine> the untagged responses of this type, e.g. `SEARCH` for `* SEARCH 1 2` or
     *                                `FETCH` for `* 4 FETCH (...)`
     */
    public function findUntagged(string $type): array
    {
        return array_values(array: array_filter(
            array: $this->untaggedLines,
            callback: static fn(ImapResponseLine $line): bool => preg_match(
                pattern: '/^\* (?:\d+ )?' . preg_quote(str: $type, delimiter: '/') . '(?: |$)/i',
                subject: $line->getText(),
            ) === 1,
        ));
    }
}
