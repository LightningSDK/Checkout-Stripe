<?php

namespace lightningsdk\checkout_stripe;

use lightningsdk\core\Tools\Cache\Cache;
use lightningsdk\core\Tools\Communicator\RestClient;
use lightningsdk\core\Tools\Configuration;

class StripeClient extends RestClient {
    public function __construct($server_address = '') {
        parent::__construct('https://api.stripe.com/v1/');
        $this->setBasicAuth(Configuration::get('stripe.private'), '');
    }

    public function getPlan($id) {
        // Attempt to load from cache.
        $cache = Cache::get();
        $cache_key = 'stripe_subscription_' . $id;
        $subscription = $cache->get($cache_key);
        if (empty($subscription)) {
            // If not in cache, load from Stripe.
            $this->callGet('plans/' . $id);
            $subscription = $this->getResults();
            $cache->set($cache_key, $subscription);
        }

        // Return the subscription.
        return $subscription;
    }

    public function getUserSubscriptions($user_id) {
        $this->callGet('customers/' . $user_id . '/subscriptions');
        return $this->get('data');
    }

    public function getUserSubscrtipionID($user_id, $subscription_id) {
        $subscriptions = self::getUserSubscriptions($user_id);
        foreach ($subscriptions as $sub) {
            if ($sub['status'] != 'active') {
                continue;
            }
            foreach ($sub['items']['data'] as $item) {
                if ($item['plan']['id'] == $subscription_id) {
                    return $item['id'];
                }
            }
        }
    }
}
