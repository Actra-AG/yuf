<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * The authentication methods that a server announces in its answer to `EHLO` (RFC 4954 section 3): the lines
 * `AUTH LOGIN PLAIN` and the legacy form `AUTH=LOGIN PLAIN`, also several of them in one answer.
 *
 * @internal
 */
final readonly class SmtpCapabilities
{
    /**
     * @param list<string> $authMethods the announced methods in upper case, also those that yuf does not know
     */
    private function __construct(
        public bool $announcesAuth,
        public array $authMethods,
    ) {}

    /**
     * @param string $reply all lines of the answer to `EHLO`, the first one is the greeting of the server
     */
    public static function fromEhloReply(string $reply): self
    {
        $announcesAuth = false;
        $authMethods = [];
        $lines = preg_split(pattern: '/\r\n|\n|\r/', subject: $reply, flags: PREG_SPLIT_NO_EMPTY);
        foreach (array_slice(array: $lines === false ? [] : $lines, offset: 1) as $line) {
            $isAuthLine = preg_match(
                pattern: '/^250[ -]AUTH(?:[ =]+(.*))?$/Di',
                subject: trim(string: $line),
                matches: $matches,
            );
            if ($isAuthLine !== 1) {
                continue;
            }
            $announcesAuth = true;
            $names = preg_split(pattern: '/[ =]+/', subject: $matches[1] ?? '', flags: PREG_SPLIT_NO_EMPTY);
            foreach ($names === false ? [] : $names as $name) {
                $authMethods[] = strtoupper(string: $name);
            }
        }

        return new self(
            announcesAuth: $announcesAuth,
            authMethods: array_values(array: array_unique(array: $authMethods)),
        );
    }

    public function supports(SmtpAuthMethodEnum $method): bool
    {
        return in_array(needle: $method->value, haystack: $this->authMethods, strict: true);
    }
}
