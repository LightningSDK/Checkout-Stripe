<?php

namespace Modules\Stripe;

use Lightning\Tools\Communicator\Client;
use Lightning\Tools\Configuration;

class StripeClient extends Client {
    public function __construct($server_address = '') {
        parent::__construct('https://api.stripe.com/v1/');
        $this->setBasicAuth(Configuration::get('stripe.private'), '');
    }
}
