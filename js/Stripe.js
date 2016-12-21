(function() {
    var self = lightning.modules.stripe = {
        meta: {},

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
                key: lightning.get('modules.stripe.public'),
                locale: 'auto',
                zipCode: true,
                billingAddress: true,
                shippingAddress: input_settings.shipping_address ? true : false,
                bitcoin: input_settings.bitcoin ? true : false,
                token: self.process
            };
            self.handler = StripeCheckout.configure(settings);
        },

        pay: function (details, callback) {
            self.callback = callback;

            // These options must be provided to the Stripe handler if they are set in details or globally.
            if (details.shipping_address || lightning.get('modules.checkout.shipping_address', false)) {
                details.shipping_address = true;
            }
            if (details.bitcoin || lightning.get('modules.checkout.bitcoin', false)) {
                details.bitcoin = true;
            }

            self.initHandler(details);
            self.meta = {};
            if (details.cart_id) {
                self.meta.cart_id = details.cart_id;
            }
            if (details.product_id) {
                self.meta.product_id = details.product_id;
            }
            if (details.create_customer) {
                self.meta.create_customer = true;
            }
            self.amount = details.amount * 100;
            self.handler.open({
                name: details.hasOwnProperty('title') ? details.title : '',
                description: '',
                amount: self.amount,
            });
        },

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
