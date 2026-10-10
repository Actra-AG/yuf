<?php

declare(strict_types=1);

return [
    'defaultErrorReporting' => E_ALL,
    'defaultTimeZone' => 'Europe/Zurich',
    'allowedDomains' => [
        'example.com.ddev.site',
    ],
    'logEmailRecipient' => 'error@example.com',
    // true only while developing: the error pages then show details of the exception
    'debug' => false,
    'robots' => 'noindex,nofollow',
    // Optional, default: the value of debug. Without checks (production), clear app/cache/v*/ on every deployment.
    'checkTemplateChanges' => true,
];
