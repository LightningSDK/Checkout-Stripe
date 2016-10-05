<?php

namespace Modules\Stripe\Model;

use Lightning\Model\Object;

class StripeCustomer extends Object {
    const TABLE = 'stripe_customer';
    const PRIMARY_KEY = 'user_id';
}
