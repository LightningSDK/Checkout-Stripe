<?php

return [
    'package' => [
        'module' => 'Stripe',
        'version' => '1.0',
    ],
    'routes' => [
        'static' => [
            'api/stripe/charge' => \Modules\Stripe\API\Charge::class,
            'api/stripe/webhook' => \Modules\Stripe\API\WebHooks::class,
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
            'init_view' => [\Modules\Stripe\Connectors\Checkout::class, 'init'],
            'use_plaid' => false,
            'webhooks' => [
                'quiet' => false,
                'private_key' => 'Replace this with your private key.',
            ]
        ]
    ]
];
