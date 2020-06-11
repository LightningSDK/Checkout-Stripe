<?php

namespace Modules\Stripe\Model;

use Lightning\Model\BaseObject;
use Lightning\Model\User;
use Modules\Stripe\StripeClient;

class StripeCustomer extends BaseObject {
    const TABLE = 'stripe_customer';
    const PRIMARY_KEY = 'user_id';

    const TYPE_CREDIT_CARD = 'card';

    protected $stripeData = null;

    public function stripeData() {
        if ($this->stripeData == null) {
            $this->loadStripeData();
        }
        return $this->stripeData;
    }

    public function loadStripeData($force = false) {
        if (!empty($this->customer_id)) {
            $client = new StripeClient();
            $client->callGet('customers/' . $this->customer_id);
            $this->stripeData = $client->getResults();
        }
    }

    public static function loadByUser(User $user, $create = true) {
        $customer = parent::loadByID($user->id);
        if (empty($user) && $create) {
            return static::createNewForUser($user);
        } else {
            return $customer;
        }
    }

    public static function createNewForUser(User $user) {
        // Create customer
        $client = new StripeClient();
        $client->set('email', $user->email);
        $client->set('description', $user->fullName());
        $client->callPost('customers');

        $customer = new static(['user_id' => $user->id, 'customer_id' => $client->get('id')]);
        $customer->__createNew = true;
        $customer->save();
        return $customer;
    }

    public function getSources() {
        $data = $this->stripeData();
        return !empty($data['sources']['data']) ? $data['sources']['data'] : [];
    }

    public function createSourceFromCard($token, $type) {
        $client = new StripeClient();
        $client->set('token', $token);
        $client->set('type', $type);
        $client->callPost('sources');
        $this->loadStripeData(true);
        return $client->get('id');
    }

    public function attachSource($source_id, $metadata = []) {
        $client = new StripeClient();
        $client->set('source', $source_id);
        $client->set('metadata', $metadata);
        $client->callPost('customers/' . $this->customer_id . '/sources');
        return $client->get('id');
    }

    /**
     * Update the default payment method for a user. This will be used for all active subscriptions.
     *
     * @param $source_id
     *   This can be the ID of a source for a credit card, or the ID of a bank account. Note that bank accounts are
     *   not wrapped in sources like credit cards.
     */
    public function setDefaultSource($source_id) {
        if ($this->stripeData()['default_source'] != $source_id) {
            $client = new StripeClient();
            $client->set('default_source', $source_id);
            $client->callPost('customers/' . $this->customer_id);
        }
    }

    /**
     * Subscribe a user to a subscription plan.
     *
     * @param string|array $subscription
     *   If string, the stripe subscription ID
     *   If array, with the following properties
     *     - plan : The Stripe subscription ID
     *     - quantity : The quantity to add
     */
    public function startSubscription($subscription) {
        $client = new StripeClient();
        $client->set('customer', $this->customer_id);
        if (is_string($subscription)) {
            $subscription = ['plan' => $subscription];
        }
        $client->set('plan', $subscription['plan']);
        $client->set('quantity', !empty($subscription['qty']) ? $subscription['qty'] : 1);
        $client->callPost('subscriptions');
        return $client->get('id');
    }
}
