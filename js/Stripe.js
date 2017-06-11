(function() {
    if (lightning.modules.stripe) {
        return;
    }
    var self = lightning.modules.stripe = {
        meta: {},
        amount: 0,

        init: function () {
            lightning.require('https://checkout.stripe.com/checkout.js', function(){
                // Close Checkout on page navigation:
                $(window).on('popstate', function () {
                    self.handler.close();
                });
            });
        },

        initHandler: function(input_settings) {
            var settings = {
                key: lightning.get('modules.stripe.public', null),
                locale: 'auto',
                zipCode: true,
                billingAddress: true,
                shippingAddress: !!input_settings.shipping_address,
                bitcoin: !!input_settings.bitcoin,
                token: self.process
            };
            if (settings.key === null) {
                lightning.dialog.show();
                lightning.dialog.add('Stripe key is not configured.', 'error');
                throw Error;
            }
            self.handler = StripeCheckout.configure(settings);
        },

        prepareTransaction: function(purchase_options) {
            // See if we need to prepare anything with the server before the transaction is submitted.
            if (purchase_options.create_customer) {
                // Submit all the data to the server. If there is anything required by the user, they will be prompted to proceed.
                $.ajax({
                    url: '/api/stripe/charge',
                    type: 'POST',
                    dataType: 'JSON',
                    data: {
                        options: purchase_options,
                        action: 'prepare'
                    }
                });
            } else {
                // This doesn't require a check with the server.
                return true;
            }
        },

        pay: function (purchase_options, callback) {
            self.callback = callback;

            // These options must be provided to the Stripe handler if they are set in details or globally.
            if (purchase_options.shipping_address || lightning.get('modules.checkout.shipping_address', false)) {
                purchase_options.shipping_address = true;
            }
            if (purchase_options.bitcoin || lightning.get('modules.checkout.bitcoin', false)) {
                purchase_options.bitcoin = true;
            }

            // Set up transaction details
            self.amount = purchase_options.amount * 100;

            // Set up stripe payment options.
            self.meta = {};
            if (purchase_options.cart_id) {
                self.meta.cart_id = purchase_options.cart_id;
            }
            if (purchase_options.product_id) {
                self.meta.product_id = purchase_options.product_id;
            }
            if (purchase_options.create_customer) {
                self.meta.create_customer = true;
            }

            if (!self.prepareTransaction(purchase_options)) {
                return false;
            }

            // Open the Stripe payment window.
            self.initHandler(purchase_options);
            self.handler.open({
                name: purchase_options.hasOwnProperty('title') ? purchase_options.title : '',
                description: '',
                amount: self.amount,
            });
        },

        /**
         * Finalize the payment.
         *
         * @param token
         *   A token returned by the Stripe checkout window.
         * @param addresses
         *   An object containing the user input addresses.
         */
        process: function (token, addresses) {
            lightning.dialog.showLoader();
            $.ajax({
                url: '/api/stripe/charge',
                type: 'POST',
                dataType: 'JSON',
                data: {
                    amount: self.amount,
                    token: token.id,
                    meta: self.meta,
                    currency: 'usd',
                    payment_data: token,
                    addresses: addresses
                },
                success: function (data) {
                    if (self.callback) {
                        self.callback();
                    }
                }
            });
        },

        callback: function () {
        }
    };
}());
