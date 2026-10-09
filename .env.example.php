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
    // Production: false, and clear the template cache on every deployment
    'checkTemplateChanges' => true,
];
