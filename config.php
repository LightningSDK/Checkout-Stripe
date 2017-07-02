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
    'js' => [
        // Module Name
        'Stripe' => [
            // Source file => Dest file
            'Stripe.js' => 'Checkout.min.js',
        ]
    ],
    'modules' => [
        'stripe' => [
            'use_plaid' => false,
        ]
    ]
];
