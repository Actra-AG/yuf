<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

/**
 * Why a `ValidatedEmailAddress` is not valid or not resolvable. The values are the codes of earlier versions.
 */
enum EmailAddressErrorEnum: string
{
    case EMPTY_VALUE = 'emptyValue';
    case AT_CHARACTER = 'atCharacterError';
    case INVALID_DOMAIN_NAME = 'invalidDomainName';
    case INVALID_SYNTAX = 'invalidSyntax';
    case INVALID_CHARACTERS = 'invalidCharacters';
    case DNS_GET_RECORD = 'dns_get_record';
    case NO_DNS_RECORDS = 'noDnsRecords';
    case FSOCKOPEN = 'fsockopen';
    case NOT_RESOLVABLE = 'notResolvable';
}
