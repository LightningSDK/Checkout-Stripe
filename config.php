<?php

return [
    'package' => [
        'module' => 'Stripe',
        'version' => '1.0',
    ],
    'routes' => [
        'static' => [
            'api/stripe/charge' => 'Modules\\Stripe\\API\\Charge',
            'api/stripe/webhook' => 'Modules\\Stripe\\API\\WebHooks',
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
            'webhooks' => [
                'quiet' => false,
                'private_key' => 'Replace this with your private key.',
            ]
        ]
    ]
];
