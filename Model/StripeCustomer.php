<?php

namespace Modules\Stripe\Model;

use Lightning\Model\Object;
use Modules\Stripe\StripeClient;

class StripeCustomer extends Object {
    const TABLE = 'stripe_customer';
    const PRIMARY_KEY = 'user_id';

    protected $stripeData = null;

    public function stripeData() {
        if ($this->stripeData == null) {
            $this->loadStripeData();
        }
        return $this->stripeData;
    }

    public function loadStripeData() {
        if (!empty($this->customer_id)) {
            $client = new StripeClient();
            $client->callGet('custoemrs/' . $this->customer_id);
            $this->stripeData = $client->getAll();
        }
    }

    public static function createNewForUser($user_id) {
        $client = new StripeClient();
        $customer = new static(['user_id' => $user_id, 'customer_id' => $client->get('id')]);
        $customer->stripeData = $client->getAll();
        $customer->save();
        return $customer;
    }

    public function addPaymentMethod($payment_data) {
        $client = new StripeClient();
        $client->set('source', [
            'object' => 'card',
            'name' => $payment_data['name'],
            'number' => $payment_data['number'],
            'exp_month' => $payment_data['exp_month'],
            'exp_year' => $payment_data['exp_year'],
            'cvc' => $payment_data['cvc'],
            'address_line1' => $payment_data['address_line1'],
            'address_line2' => $payment_data['address_line2'],
            'address_city' => $payment_data['address_city'],
            'address_state' => $payment_data['address_state'],
            'address_zip' => $payment_data['address_zip'],
            'address_country' => $payment_data['address_country'],
        ]);
        $client->callPost('customers/' . $this->customer_id . '/sources');
        $this->loadStripeData();
    }

    public function startSubscription($subscription) {
        $client = new StripeClient();
        $client->set('cuserom', $this->customer_id);
        $client->set('plan', $subscription);
        $client->callPost('subscriptions');
    }
}
