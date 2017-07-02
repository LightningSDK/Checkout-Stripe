<?php

namespace Modules\Stripe;

use Lightning\Tools\Cache\Cache;
use Lightning\Tools\Communicator\RestClient;
use Lightning\Tools\Configuration;

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
}
