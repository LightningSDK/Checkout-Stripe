<?php

namespace Modules\Stripe\API;

use Lightning\Model\User;
use Lightning\Tools\Communicator\RestClient;
use Lightning\Tools\Configuration;
use Lightning\Tools\Mailer;
use Lightning\Tools\Messenger;
use Lightning\Tools\Output;
use Lightning\Tools\Request;
use Lightning\View\API;
use Modules\Checkout\Model\Address;
use Modules\Checkout\Model\Order;
use Modules\Checkout\Model\Product;
use Modules\Stripe\Model\StripeCustomer;

class Charge extends API {

    const BILLING = 'billing';
    const SHIPPING = 'shipping';

    /**
     * @var Order
     */
    protected $order;
    protected $amount;
    protected $token;
    protected $currency;
    protected $meta;
    protected $payment_response;

    /**
     * @var RestClient
     */
    protected $client;

    /**
     * @var boolean
     */
    protected $createCustomer;

    /**
     * @var integer
     */
    protected $customProductEmail;

    /**
     * @var Address
     */
    protected $shipping_address;

    /**
     * @var Address
     */
    protected $billing_address;

    protected $transactionId;

    /**
     * @var User
     */
    protected $user;

    public function post() {
        // Create the charge on Stripe's servers - this will charge the user's card
        $this->amount = Request::post('amount', Request::TYPE_INT);
        $this->token = Request::post('token');
        $this->currency = Request::post('currency');
        $this->meta = Request::post('meta', Request::TYPE_ASSOC_ARRAY);
        $this->payment_response = Request::post('payment_data', Request::TYPE_ASSOC_ARRAY);
        $this->createCustomer = (!empty($this->meta['create_customer']) && $this->meta['create_customer'] == 'true');

        $this->client = new RestClient('https://api.stripe.com/v1/');
        $this->client->setBasicAuth(Configuration::get('stripe.private'), '');
        if ($this->createCustomer) {
            $this->createAndChargeCustomer();
        } else {
            $this->chargeToken();
        }

        $this->createOrSaveOrder();

        if (empty($this->order)) {
            throw new \Exception('Invalid Order');
        }

        $this->addAddresses();
        $this->order->save();

        // Add the payment to the database.
        $payment = $this->order->addPayment($this->amount, $this->currency, $this->token, [
            'billing_address' => $this->billing_address->id,
            'time' => $this->payment_response['created'],
            'details' => [
                'metadata' => $this->meta,
                'payment_data' => $this->payment_response,
            ]
        ]);

        $this->sendNotifications();

        return Output::SUCCESS;
    }

    protected function createAndChargeCustomer() {
        // Create the customer.
        $description = '';
        if (!empty($this->payment_response['card']['name'])) {
            $description .= $this->payment_response['card']['name'] . ' ';
        }
        if (!empty($this->payment_response['email'])) {
            $description .= $this->payment_response['email'] . ' ';
        }
        $this->client->set('description', $description);
        $this->client->set('source', $this->token);
        $this->client->callPost('customers');

        if ($this->client->hasErrors()) {
            Output::error($this->client->getErrors());
            return Output::ERROR;
        }

        $customer_id = $this->client->get('id');

        // Charge the customer.
        $this->client->clearRequestVars();
        $this->client->set('amount', $this->amount);
        $this->client->set('currency', $this->currency);
        $this->client->set('customer', $customer_id);
        $this->client->callPost('charges');

        if ($this->client->hasErrors()) {
            Output::error($this->client->getErrors());
            return Output::ERROR;
        }

        $this->transactionId = $this->client->get('id');

        // Create a customer entry.
        $user = User::addUser($this->payment_response['email']);
        $customer = StripeCustomer::loadByID($user->id);
        if ($customer) {
            $customer->customer_id = $customer_id;
            $customer->save();
        } else {
            $customer = new StripeCustomer([
                'user_id' => $user->id,
                'customer_id' => $customer_id,
            ]);
            $customer->save();
        }
    }

    protected function chargeToken() {
        $this->client->set('amount', $this->amount);
        $this->client->set('currency', $this->currency);
        $this->client->set('source', $this->token);
        $this->client->set('metadata', $this->meta);
        if ($descriptor = Configuration::get('stripe.statement_descriptor')) {
            $this->client->set('statement_descriptor', substr(preg_replace('/[^a-z0-9 ]/i', '', $descriptor), 0, 22));
        }
        $this->client->callPost('charges');

        if ($this->client->hasErrors()) {
            Output::error($this->client->getErrors());
            return Output::ERROR;
        }

        $this->transactionId = $this->client->get('id');
    }

    protected function createOrSaveOrder() {
        if (!empty($this->meta['cart_id'])) {
            $order_id = intval($this->meta['cart_id']);
            if (empty($order_id)) {
                throw new \Exception('Invalid Order Id');
            }

            // Payment for an existing order or cart.
            $this->order = Order::loadBySession($order_id);
            if ($this->order->getTotal() * 100 != $this->amount) {
                throw new \Exception('Invalid Amount');
            }
            $this->order->paid = $this->payment_response['created'];
            $this->order->gateway_id = $this->transactionId;
            $this->order->locked = 1;
            $this->order->details->payments[] = [
                'metadata' => $this->meta,
                'payment_data' => $this->payment_response,
            ];
        } else {
            // Create a new order.
            $this->order = new Order([
                'total' => $this->amount,
                'time' => time(),
                'paid' => $this->payment_response['created'],
                'gateway_id' => $this->transactionId,
                'details' => [
                    'payments' => [
                        'metadata' => $this->meta,
                        'payment_data' => $this->payment_response,
                    ]
                ],
            ]);
            $this->order->save();
            if (!empty($this->meta['product_id'])) {
                $this->order->addItem($this->meta['product_id'], 1);
                $product = Product::loadByID($this->meta['product_id']);
                $this->customProductEmail = $product->options->customer_email;
            }
        }
    }

    /**
     * @param string $type
     *
     * @return Address
     */
    protected function getAddress($type) {
        static $addresses = null;
        if ($addresses === null) {
            $addresses = Request::post('addresses', 'assoc_array');
        }
        if (empty($addresses[$type . '_name'])) {
            // This address is not present.
            return null;
        }
        return new Address([
            'name' => $addresses[$type . '_name'],
            'street' => $addresses[$type . '_address_line1'],
            'street2' => !empty($addresses[$type . '_address_line2']) ? $addresses[$type . '_address_line2'] : '',
            'city' => $addresses[$type . '_address_city'],
            'state' => $addresses[$type . '_address_state'] ?: '',
            'zip' => $addresses[$type . '_address_zip'],
            'country' => $addresses[$type . '_address_country_code'],
        ]);
    }

    protected function addAddresses() {

        if (empty($this->order->shipping_address)) {
            // TODO: Check if the address already exists first.

            if ($this->shipping_address = $this->getAddress(static::SHIPPING)) {
                $this->shipping_address->save();
                $this->order->shipping_address = $this->shipping_address->id;
            }
        }

        $this->user = User::addUser($this->payment_response['email'], [
            'full_name' => !empty($this->payment_response['card']['name'])
                ? $this->payment_response['card']['name']
                : Request::post('addresses.billing_name', Request::TYPE_STRING, '')
        ]);
        $this->order->user_id = $this->user->id;

        // TODO: Also check if this exists.
        $this->billing_address = $this->getAddress(static::BILLING);
        if (!empty($this->shipping_address) && $this->shipping_address->equalsData($this->billing_address)) {
            $this->billing_address = $this->shipping_address;
        } else {
            $this->billing_address->save();
        }
    }

    protected function sendNotifications() {
        // Set Meta Data for email.
        $mailer = new Mailer();
        $mailer->setCustomVariable('META', $this->meta);
        if ($this->shipping_address) {
            $mailer->setCustomVariable('SHIPPING_ADDRESS_BLOCK', $this->shipping_address->getHTMLFormatted());
        }

        $mailer->setCustomVariable('ORDER_DETAILS', $this->order->formatContents());

        // Send emails.
        if (!empty($this->customProductEmail)) {
            $mailer->sendOne($this->customProductEmail, $this->user);
        }
        if ($buyer_email = Configuration::get('stripe.buyer_email')) {
            $mailer->sendOne($buyer_email, $this->user);
        }
        if ($seller_email = Configuration::get('stripe.seller_email')) {
            $mailer->sendOne($seller_email, Configuration::get('contact.to')[0]);
        }

        Messenger::message('Your order has been processed!');
    }
}
