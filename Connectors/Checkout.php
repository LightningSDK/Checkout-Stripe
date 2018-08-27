<?php

namespace Modules\Stripe\Connectors;

use Lightning\Tools\Configuration;
use Lightning\Tools\Template;
use Lightning\View\JS;
use Modules\Checkout\Handlers\Payment;
use Modules\Checkout\Model\Order;
use Modules\Stripe\StripeClient;

class Checkout extends Payment {
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

        return '$' . number_format($subscription['amount']/100, 2) . ' per ' . $subscription['interval'];
    }

    public function getDescription() {
        return 'Pay with Visa, MasterCard, Discover or American Express';
    }

    public function getTitle() {
        return 'Credit card';
    }

    public function getPage(Order $cart) {
        if ($cart->hasSubscription()) {
            return ['payment-source', 'Stripe'];
        } else {
            JS::set('modules.stripe.public', Configuration::get('stripe.public'));
            JS::startup('lightning.modules.stripe.initElementsCard()', ['https://js.stripe.com/v3/']);
            $order = Order::loadBySession();
            JS::set('modules.checkout.cart', [
                'id' => $order->id,
                'amount' => intval($order->getTotal() * 100),
                'name' => $cart->requiresShippingAddress() ? $cart->getShippingAddress()->name : '',
            ]);

            $user = $order->getUser();
            Template::getInstance()->set('email', !empty($user) ? $user->email : '');

            return ['checkout-payment', 'Stripe'];
        }
    }
}
