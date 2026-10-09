<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Regular expressions of the phone number parser.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 *
 * @internal
 */
final class PhonePatterns
{
    public const string VALID_START_CHAR_PATTERN = '[' . PhoneConstants::PLUS_CHARS . PhoneConstants::DIGITS . ']';
    // Trailing characters that are neither a number nor a letter, nor "#" (marks an extension)
    public const string UNWANTED_END_CHAR_PATTERN = '[^\\p{N}\\p{L}#]+$';
    public const string SECOND_NUMBER_START_PATTERN = '[\\\\/] *x';
    public const string MIN_LENGTH_PHONE_NUMBER_PATTERN = '[' . PhoneConstants::DIGITS . ']{'
        . PhoneConstants::MIN_LENGTH_FOR_NSN . '}';
    public const string EXTN_PATTERNS_FOR_PARSING = PhoneConstants::RFC3966_EXTN_PREFIX
        . PhoneConstants::CAPTURING_EXTN_DIGITS . '|' . "[ \xC2\xA0\\t,]*"
        . "(?:e?xt(?:ensi(?:o\xCC\x81?|\xC3\xB3))?n?|(?:\xEF\xBD\x85)?\xEF\xBD\x98\xEF\xBD\x94"
        . "(?:\xEF\xBD\x8E)?|"
        . 'доб|' . '[' . ',;' . "x\xEF\xBD\x98#\xEF\xBC\x83~\xEF\xBD\x9E"
        . "]|int|\xEF\xBD\x89\xEF\xBD\x8E\xEF\xBD\x94|anexo)"
        . "[:\\.\xEF\xBC\x8E]?[ \xC2\xA0\\t,-]*" . PhoneConstants::CAPTURING_EXTN_DIGITS . '\\#?|'
        . '[- ]+(' . PhoneConstants::DIGITS . '{1,5})\\#';
    public const string VALID_PHONE_NUMBER_PATTERN = '%^' . PhonePatterns::MIN_LENGTH_PHONE_NUMBER_PATTERN . '$|^'
        . PhoneConstants::VALID_PHONE_NUMBER . '(?:' . PhonePatterns::EXTN_PATTERNS_FOR_PARSING . ')?$%'
        . PhoneConstants::REGEX_FLAGS;
    public const string PLUS_CHARS_PATTERN = '[' . PhoneConstants::PLUS_CHARS . ']+';
    public const string VALID_ALPHA_PHONE_PATTERN = '(?:.*?[A-Za-z]){3}.*';
    public const string CAPTURING_DIGIT_PATTERN = '(' . PhoneConstants::DIGITS . ')';
    // The first group of a format (`$1`), where the national prefix formatting rule is inserted
    public const string FIRST_GROUP_PATTERN = '/(\\$\\d)/';
    public const string EXTN_PATTERN = '/' . PhonePatterns::EXTN_PATTERNS_FOR_PARSING . '$/'
        . PhoneConstants::REGEX_FLAGS;
}
