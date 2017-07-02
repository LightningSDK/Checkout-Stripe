<?php

namespace Modules\Stripe\Connectors;

use Lightning\Tools\Configuration;
use Lightning\View\JS;
use Modules\Stripe\StripeClient;

class Checkout {
    public static function init() {
        JS::startup('lightning.modules.stripe.init();', ['Stripe' => 'Stripe.js']);
        JS::set('modules.stripe.public', Configuration::get('stripe.public'));
        if (Configuration::get('modules.stripe.use_plaid')) {
            JS::set('modules.plaid.public_key', Configuration::get('modules.plaid.public_key'));
        }
        JS::set('modules.checkout.handler', 'lightning.modules.stripe.pay');
    }

    public static function printPlan($id) {
        $stripe = new StripeClient();
        $subscription = $stripe->getPlan($id);

        return '$' . $subscription['amount'] . ' per ' . $subscription['interval'];
    }
}
