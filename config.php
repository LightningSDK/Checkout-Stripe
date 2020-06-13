<?php

return [
    'package' => [
        'module' => 'Stripe',
        'version' => '1.0',
    ],
    'routes' => [
        'static' => [
            'api/stripe/charge' => \lightningsdk\checkout_stripe\API\Charge::class,
            'api/stripe/webhook' => \lightningsdk\checkout_stripe\API\WebHooks::class,
        ]
    ],
    'compiler' => [
        'js' => [
            // Module Name
            'lightningsdk/checkout-stripe' => [
                // Source file => Dest file
                'Stripe.js' => 'Checkout.min.js',
            ]
        ],
    ],
    'modules' => [
        'stripe' => [
            'init_view' => [\lightningsdk\checkout_stripe\Connectors\Checkout::class, 'init'],
            'use_plaid' => false,
            'webhooks' => [
                'quiet' => false,
                'private_key' => 'Replace this with your private key.',
            ]
        ]
    ]
];
