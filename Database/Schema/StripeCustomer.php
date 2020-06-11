<?php

namespace lightningsdk\checkout_stripe\Database\Schema;

use lightningsdk\core\Database\Schema;

class StripeCustomer extends Schema {

    const TABLE = 'stripe_customer';

    public function getColumns() {
        return [
            'user_id' => $this->int(true),
            'customer_id' => $this->char(32),
        ];
    }

    public function getKeys() {
        return [
            'primary' => 'user_id',
        ];
    }
}
