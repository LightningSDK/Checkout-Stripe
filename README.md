# Lightning-Stripe: Stripe payment processor for Lightning-Checkout

# What it does:

Adds Stripe payment processing to Checkout.

# Installation and Configuration
```
$conf = [
    'modules' => [
        'stripe' => [
            // A thankyou email message id that is sent to the buyer on
            // successful purchase.
            'buyer_email' => {int}
            
            // An alert message to send to the seller when an order
            // has been made.
            'seller_email' => {int}
            
            // The descriptoer to appear on the credit card statement
            // of the buyer
            'statement_descriptor' => {string}
            
            // Whether to allow bitcoin payment processing.
            'bitcoin' => false,
            
            // Public key for processing transactions
            'public' => {string},
            
            // Private key for processing transactions
            'private' => {string},
        ]
    ]
];
```
