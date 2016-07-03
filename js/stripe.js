(function() {
    var self;
    lightning.modules.stripe = {
        meta: {},

        init: function () {
            lightning.require('https://checkout.stripe.com/checkout.js', function(){
                // Create the handler.
                self.initHandler();

                // Close Checkout on page navigation:
                $(window).on('popstate', function () {
                    handler.close();
                });
            });
        },

        initHandler: function() {
            self.handler = StripeCheckout.configure({
                key: lightning.get('modules.stripe.public'),
                locale: 'auto',
                zipCode: true,
                shippingAddress: true,
                billingAddress: true,
                bitcoin: true,
                token: self.process
            });
        },

        pay: function (details, callback) {
            self.callback = callback;
            self.initHandler();
            self.meta = {};
            if (details.cart_id) {
                self.meta.cart_id = details.cart_id;
            }
            self.amount = details.amount * 100;
            self.handler.open({
                name: '',
                description: '',
                amount: self.amount,
            });
        },

        process: function (token, addresses) {
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
                    self.callback();
                }
            });
        },

        callback: function () {
        }
    };
    self = lightning.modules.stripe;
}());
