<?php
return [
    'defaultErrorReporting' => E_ALL,
    'defaultTimeZone' => 'Europe/Zurich',
    'allowedDomains' => [
        'example.com.ddev.site',
    ],
    'logEmailRecipient' => 'error@example.com',
    'debug' => true,
    'robots' => 'noindex,nofollow',
    // Optional, default: the value of debug. Without checks (production), clear app/cache/v*/ on every deployment
    'checkTemplateChanges' => true,
];
