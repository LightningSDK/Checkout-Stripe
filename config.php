<?php

return [
    'package' => [
        'module' => 'Stripe',
        'version' => '1.0',
    ],
    'routes' => [
        'static' => [
            'api/stripe/charge' => 'Modules\\Stripe\\API\\Charge',
        ]
    ],
];
