<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

// Environment settings of the example (in a real project: .env.php, not committed)
return [
    'defaultErrorReporting' => E_ALL,
    'defaultTimeZone' => 'Europe/Zurich',
    'allowedDomains' => [
        'yuf.ddev.site',
    ],
    'logEmailRecipient' => 'error@example.com',
    'debug' => true,
    'robots' => 'noindex,nofollow',
];