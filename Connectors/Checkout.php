<?php

namespace Modules\Stripe\Connectors;

use Lightning\Tools\Configuration;
use Lightning\View\JS;

class Checkout {
    public static function init() {
        JS::startup('lightning.modules.stripe.init();', ['Stripe' => 'Stripe.js']);
        JS::set('modules.stripe.public', Configuration::get('stripe.public'));
        JS::set('modules.checkout.handler', 'lightning.modules.stripe.pay');
    }
}
