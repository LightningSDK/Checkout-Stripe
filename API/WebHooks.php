<?php

namespace Modules\Stripe\API;

use Exception;
use Lightning\Tools\Configuration;
use Lightning\Tools\Request;
use Lightning\View\API;

class WebHooks extends API {

    protected $data;
    protected $quiet;

    public function __construct() {
        $this->quiet = Configuration::get('modules.stripe.webhooks.quiet');
        $this->verifySignature();
        parent::__construct();
    }

    public function post() {
        $this->data = Request::allJson();

        $method = Request::convertFunctionName(preg_replace('/\./', ' ', $this->data['type']));
        if (is_callable([$this, $method])) {
            $this->$method();
        } else {
            throw new Exception('No handler for this ');
        }
    }

    protected function verifySignature() {
        $signature = Request::getHeader('Stripe-Signature');
        $signatures = explode(',', $signature);
        foreach ($signatures as $s) {
            $s = explode('=', $s);
            if ($s[0] == 't') {
                $timestamp = $s[1];
            }
            if ($s[0] == 'v1') {
                $signature = $s[1];
            }
        }
        $signedData = $timestamp . '.' . Request::getBody();
        $verifiedNewSignature = hash_hmac('sha256', $signedData, Configuration::get('modules.stripe.webhooks.private_key'));

        if ($verifiedNewSignature != $signature) {
            throw new Exception('Invalid Signature');
        }
    }
}
