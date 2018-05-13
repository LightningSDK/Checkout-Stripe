(function() {
    if (lightning.modules.stripe) {
        return;
    }
    var self = lightning.modules.stripe = {
        meta: {},
        amount: 0,

        init: function () {
            lightning.js.require('https://checkout.stripe.com/checkout.js', function(){
                // Close Checkout on page navigation:
                $(window).on('popstate', function () {
                    self.handler.close();
                });
            });
        },

        initElementsCard: function() {
            // Create a Stripe client.
            var stripe = Stripe(lightning.get('modules.stripe.public', null));

            // Create an instance of Elements.
            var elements = stripe.elements();

            // Custom styling can be passed to options when creating an Element.
            // (Note that this demo uses a wider set of styles than the guide below.)
            var style = {
                base: {
                    color: '#32325d',
                    lineHeight: '18px',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            };

            // Create an instance of the card Element.
            var card = elements.create('card', {style: style});

            // Add an instance of the card Element into the `card-element` <div>.
            card.mount('#card-element');

            // Handle the form submission
            var form = $('#payment-form');
            form.on('valid.fndtn.abide', function(event) {
                lightning.dialog.showLoader('Processing your payment...');
                event.preventDefault();
                var tokenData = {
                    email: form.find('input[name=email]').val(),
                    name: lightning.get('modules.checkout.cart.name')
                };
                var cartId = lightning.get('modules.checkout.cart.id');
                stripe.createToken(card, tokenData).then(function(result) {
                    if (result.error) {
                        // Inform the customer that there was an error.
                        var errorElement = document.getElementById('card-errors');
                        errorElement.textContent = result.error.message;
                    } else {
                        // Send the token to your server.
                        self.amount = lightning.get('modules.checkout.cart.amount');
                        self.meta = {
                            cart_id: cartId
                        };
                        self.process(result.token, null);
                        self.callback = function() {
                            document.location = '/store/checkout?page=confirmation&cart_id=' + cartId
                        };
                    }
                });
                return false;
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

        /**
         * Initialization required for the modal window where a user can select from a list of available payment
         * sources or add a new credit card (or bank account if plaid is enabled).
         *
         * @param payment_options
         */
        initPaymentSource: function(payment_options) {
            lightning.js.require('https://js.stripe.com/v3/', function(){
                var stripe = Stripe(lightning.get('modules.stripe.public'));
                var elements = stripe.elements();
                var card = elements.create('card', {style: {base: {
                    color: '#32325d',
                    lineHeight: '24px',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }}});
                card.mount('#card-element');

                card.addEventListener('change', function(event) {
                    var displayError = document.getElementById('card-errors');
                    if (event.error) {
                        displayError.textContent = event.error.message;
                    } else {
                        displayError.textContent = '';
                    }
                });

                // Handle form submission
                var form = $('#stripe-payment-form');
                var selectField = form.find('#source-select');
                var cardRow = form.find('.new-card');
                var bankRow = form.find('.new-bank');

                selectField.on('change', function(){
                    cardRow.toggle(selectField.val() === 'new-card');
                    bankRow.toggle(selectField.val() === 'new-bank');
                });
                selectField.trigger('change');

                form.find('#new-bank-signin').on('click', function(){
                    lightning.modules.plaid.connect();
                });

                form.on('submit', function(event){
                    event.preventDefault();

                    switch(selectField.val()) {
                        case undefined:
                        case 'new-card':
                            stripe.createToken(card).then(function(result) {
                                if (result.error) {
                                    // Inform the user if there was an error
                                    var errorElement = document.getElementById('card-errors');
                                    errorElement.textContent = result.error.message;
                                } else {
                                    // Send the token to your server
                                    payment_options.source = result.token;
                                    self.prepareTransaction(payment_options);
                                }
                            });
                            break;
                        case 'new-bank':
                            lightning.modules.plaid.connect();
                            break;
                        default:
                            payment_options.source = selectField.val();
                            self.prepareTransaction(payment_options);
                    }
                });
            });
        },

        addAndSelectBankOption: function(id, description) {
            var form = $('#stripe-payment-form');
            var selectField = form.find('#source-select');
            selectField.append($('<option>').prop('value', id).text(description));
            selectField.val(id);
            selectField.trigger('change');
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
                    },
                    success: function(data) {
                        if (data.hasOwnProperty('form')) {
                            lightning.dialog.setContent(data.form);
                        }
                        if (data.hasOwnProperty('init')) {
                            var callback = lightning.getMethodReference(data.init);
                            callback(purchase_options);
                        }
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
