<?php

namespace Modules\Stripe\Database\Schema;

use Lightning\Database\Schema;

class StripeCustomer extends Schema {

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
