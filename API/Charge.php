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

class Charge extends API {
    public function post() {
        // Create the charge on Stripe's servers - this will charge the user's card
        $client = new RestClient('https://api.stripe.com/v1/charges');
        $amount = Request::post('amount', 'int');
        $token = Request::post('token');
        $currency = Request::post('currency');
        $meta = Request::post('meta', 'assoc_array');
        $payment_response = Request::post('payment_data', 'assoc_array');
        $client->set('amount', $amount);
        $client->set('currency', $currency);
        $client->set('source', $token);
        $client->set('metadata', $meta);
        if ($descriptor = Configuration::get('stripe.statement_descriptor')) {
            $client->set('statement_descriptor', substr(preg_replace('/[^a-z0-9 ]/i', '', $descriptor), 0, 22));
        }
        $client->setBasicAuth(Configuration::get('stripe.private'), '');
        $client->callPost();
        if ($client->hasErrors()) {
            Output::error($client->getErrors());
            return Output::ERROR;
        }

        if ($order_id = Request::post('order_id', 'int')) {
            // Payment for an existing order or cart.
            $order = Order::loadByID($order_id);
            $order->details[] = [
                'metadata' => $meta,
                'payment_data' => $payment_response,
            ];
        } elseif (Request::post('create_order', 'boolean')) {
            // Create a new order.
            $order = new Order([
                'total' => $amount,
                'time' => $payment_response['created'],
                'gateway_id' => $token,
                'details' => [
                    'metadata' => $meta,
                    'payment_data' => $payment_response,
                ],
            ]);
        }

        if (!empty($order)) {
            $addresses = Request::post('addresses', 'assoc_array');

            if (empty($order->shipping_address)) {
                // TODO: Check if the address already exists first.

                $shipping_address = new Address([
                    'name' => $addresses['shipping_name'],
                    'street' => $addresses['shipping_address_line1'],
                    'street2' => !empty($addresses['shipping_address_line2']) ? $addresses['shipping_address_line2'] : '',
                    'city' => $addresses['shipping_address_city'],
                    'state' => $addresses['shipping_address_state'],
                    'zip' => $addresses['shipping_address_zip'],
                    'country' => $payment_response['card']['country'],
                ]);
                $shipping_address->save();
                $order->shipping_address = $shipping_address->id;
            }

            $user = User::addUser($payment_response['email'], [
                'full_name' => $payment_response['card']['name']
            ]);
            $order->user_id = $user->id;

            // TODO: Also check if this exists.
            $billing_address = new Address([
                'name' => $addresses['billing_name'],
                'street' => $addresses['billing_address_line1'],
                'street2' => !empty($addresses['billing_address_line2']) ? $addresses['billing_address_line2'] : '',
                'city' => $addresses['billing_address_city'],
                'state' => $addresses['billing_address_state'],
                'zip' => $addresses['billing_address_zip'],
                'country' => $payment_response['card']['country'],
            ]);
            if (!empty($shipping_address) && $shipping_address->equalsData($billing_address)) {
                $billing_address = $shipping_address;
            } else {
                $billing_address->save();
            }

            // Add the payment to the database.
            $payment = $order->addPayment($amount, $currency, $token, [
                'billing_address' => $billing_address->id,
                'time' => $payment_response['created'],
                'details' => [
                    'metadata' => $meta,
                    'payment_data' => $payment_response,
                ]
            ]);

            $order->save();

            // Set Meta Data for email.
            $mailer = new Mailer();
            $mailer->setCustomVariable('META', $meta);
            $mailer->setCustomVariable('SHIPPING_ADDRESS_BLOCK', $shipping_address->name . '<br>' . $shipping_address->street . ' ' . $shipping_address->street2 . '<br>' . $shipping_address->city . ', ' . $shipping_address->state . ' ' . $shipping_address->zip);

            // Send emails.
            if ($buyer_email = Configuration::get('stripe.buyer_email')) {
                $mailer->sendOne($buyer_email, $user);
            }
            if ($seller_email = Configuration::get('stripe.seller_email')) {
                $mailer->sendOne($seller_email, Configuration::get('contact.to')[0]);
            }

            Messenger::message('Your order has been processed!');
            return Output::SUCCESS;
        }
    }
}
