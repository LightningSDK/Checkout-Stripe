<?php

namespace Modules\Stripe\API;

use Exception;
use Lightning\Model\User;
use Lightning\Tools\ClientUser;
use Lightning\Tools\Communicator\RestClient;
use Lightning\Tools\Configuration;
use Lightning\Tools\Mailer;
use Lightning\Tools\Messenger;
use Lightning\Tools\Output;
use Lightning\Tools\Request;
use Lightning\Tools\Template;
use Lightning\View\API;
use Modules\Checkout\Model\Address;
use Modules\Checkout\Model\LineItem;
use Modules\Checkout\Model\Order;
use Modules\Checkout\Model\Product;
use Modules\Stripe\Model\StripeCustomer;
use Modules\Stripe\StripeClient;

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

    /**
     * @return int
     * @throws Exception
     */
    public function post() {
        // Create the charge on Stripe's servers - this will charge the user's card
        $this->amount = Request::post('amount', Request::TYPE_INT);
        $this->token = Request::post('token');
        $this->currency = Request::post('currency');
        $this->meta = Request::post('meta', Request::TYPE_ASSOC_ARRAY);
        $this->payment_response = Request::post('payment_data', Request::TYPE_ASSOC_ARRAY);
        $this->createCustomer = (!empty($this->meta['create_customer']) && $this->meta['create_customer'] == 'true');

        $this->client = new StripeClient();

        if ($this->createCustomer) {
            // TODO: If this fails, it returns OUTPUT::ERROR and tries to continue - it should abort.
            $this->createAndChargeCustomer();
        } else {
            $this->chargeToken();
        }

        $this->createOrSaveOrder();

        if (empty($this->order)) {
            throw new Exception('Invalid Order');
        }

        $this->addAddresses();
        $this->order->save();

        // Add the payment to the database.
        $payment = $this->order->addPayment($this->amount, $this->currency, $this->token, [
            'billing_address' => $this->order->billing_address ?: $this->order->shipping_address,
            'time' => $this->payment_response['created'],
            'details' => [
                'metadata' => $this->meta,
                'payment_data' => $this->payment_response,
            ]
        ]);

        $this->order->sendNotifications();

        return Output::SUCCESS;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function postPrepare() {
        $options = Request::post('options', Request::TYPE_ASSOC_ARRAY);
        if (!empty($options['product_id'])) {
            $product = Product::loadByID($options['product_id']);
        }

        if ((!empty($options['create_customer']) && $options['create_customer'] === 'true') || !empty($product->options['create_customer'])) {
            // Make sure the user is logged in.
            $user = ClientUser::getInstance();
            if ($user->isAnonymous()) {
                return Output::LOGIN_REQUIRED;
            }

            // Make sure a Stripe customer exists.
            $customer = StripeCustomer::loadById($user->id);
            if (!$customer) {
                $customer = StripeCustomer::createNewForUser($user);
            }

            // Get the payment source field.
            if (!empty($options['source'])) {
                if (is_array($options['source'])) {
                    // This will be an array if they just entered a new credit card.
                    $source_id = $customer->createSourceFromCard($options['source']['id'], StripeCustomer::TYPE_CREDIT_CARD);
                    $customer->attachSource($source_id);
                } else {
                    // This will be a string if they selected an existing source.
                    $source_id = $options['source'];
                }
                $customer->setDefaultSource($source_id);
            } else {
                // TODO: If payment already exists, give option.
                $template = new Template();
                if (!empty($product)) {
                    $template->set('product', $product);
                }
                $template->set('sources', $customer->getSources());
                return [
                    'form' => $template->build(['payment-source', 'Stripe'], true),
                    'init' => 'lightning.modules.stripe.initPaymentSource',
                ];
            }

            // Create a cart
            $order = new Order();
            if ($product) {
                // If this is a managed item, add it.
                $product_options = !empty($options['item_options']) ? $options['item_options'] : [];
                $item_id = $order->addItem($product, Request::post('qty', Request::TYPE_INT) ?: 1, $product_options);
                $item = LineItem::loadByID($item_id);
            } else {
                // Add a custom item.
            }

            // If this is a subscription, pay it now.
            if (!empty($product->options['subscription'])) {
                $subscription = is_array($product->options['subscription']) ? $product->options['subscription'] : ['plan' => $product->options['subscription']];
                $subscription['qty'] = $item->qty;
                $item_options = $item->options;
                $item_options['subscription_id'] = $customer->startSubscription($subscription);
                $item->options = $item_options;
                $item->save();
                Messenger::message('Your subscription has been activated.');
            }

            // TODO: If this is a cart, pay it now.

            if (!empty($product->options['after_purchase'])) {
                if (is_callable(($product->options['after_purchase']))) {
                    $product->options['after_purchase']($order, $item);
                }
            }

            return Output::SUCCESS;
        }

        throw new Exception('Problem processing the payment');
    }

    /**
     * @return int
     * @throws Exception
     */
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
        $data = [];
        if ($ref = ClientUser::getReferrer()) {
            // Set the referrer.
            $data['referrer'] = $ref;
        }
        $user = User::addUser($this->payment_response['email'], $data);
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

    /**
     * @return int
     */
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

    /**
     * @throws Exception
     */
    protected function createOrSaveOrder() {
        if (!empty($this->meta['cart_id'])) {
            $order_id = intval($this->meta['cart_id']);
            if (empty($order_id)) {
                throw new Exception('Invalid Order Id');
            }

            // Payment for an existing order or cart.
            $this->order = Order::loadBySession($order_id);
            if (intval($this->order->getTotal() * 100) != intval($this->amount)) {
                throw new Exception('Invalid Amount');
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
        $address_map = [
            'name' => '_name',
            'street' => '_address_line1',
            'street2' => '_address_line2',
            'city' => '_address_city',
            'state' => '_address_state',
            'zip' => '_address_zip',
            'country' => '_address_country_code'
        ];

        $new_address = [];
        foreach ($address_map as $new => $old) {
            $new_address[$new] = !empty($addresses[$type . $old]) ? $addresses[$type . $old] : '';
        }

        return new Address($new_address);
    }

    /**
     * @throws Exception
     */
    protected function addAddresses() {

        if (empty($this->order->shipping_address)) {
            // TODO: Check if the address already exists first.

            if ($this->shipping_address = $this->getAddress(static::SHIPPING)) {
                $this->shipping_address->save();
                $this->order->shipping_address = $this->shipping_address->id;
            }
        }

        $data = [[
            'full_name' => !empty($this->payment_response['card']['name'])
                ? $this->payment_response['card']['name']
                : Request::post('addresses.billing_name', Request::TYPE_STRING, '')
        ]];
        if ($ref = ClientUser::getReferrer()) {
            // Set the referrer.
            $data['referrer'] = $ref;
        }
        $this->user = User::addUser($this->payment_response['email'], $data);
        $this->order->user_id = $this->user->id;

        // TODO: Also check if this exists.
        $this->billing_address = $this->getAddress(static::BILLING);
        if (!empty($this->shipping_address) && $this->shipping_address->equalsData($this->billing_address)) {
            $this->billing_address = $this->shipping_address;
        } elseif (!empty($this->billing_address)) {
            $this->billing_address->save();
        }
    }
}
