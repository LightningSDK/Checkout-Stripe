<?php

use Lightning\Tools\Configuration;

?><div class="row">
    <div class="small-12 medium-6 medium-offset-3">
        <form action="/charge" method="post" id="stripe-payment-form">
            <?= \Lightning\Tools\Form::renderTokenInput(); ?>
            <?php if (!empty($sources) || Configuration::get('modules.stripe.use_plaid')): ?>
                <div class="form-row">
                    Charge to an existing card:
                    <label for="source-select">
                        Credit or debit card
                    </label>
                    <select name="source" id="source-select">
                        <option value="new-card">New Card</option>
                        <?php if (Configuration::get('modules.stripe.use_plaid')): ?>
                            <option value="new-bank">New Bank Account</option>
                        <?php endif; ?>
                        <?php foreach($sources as $source): ?>
                            <?php if (!empty($source['card'])): ?>
                                <option value="<?= $source['id']; ?>">**** **** **** <?= $source['card']['last4']; ?> Exp: <?= $source['card']['exp_month']; ?>/<?= $source['card']['exp_year']; ?></option>
                            <?php endif; ?>
                            <?php if (!empty($source['object']) && $source['object'] === 'bank_account'): ?>
                                <option value="<?= $source['id']; ?>"><?= $source['metadata']['institution']; ?> - <?= $source['metadata']['account_name']; ?> - Ending in <?= $source['last4']; ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-row new-card">
                <label for="card-element">
                    Credit or debit card
                </label>
                <div id="card-element">
                    <!-- a Stripe Element will be inserted here. -->
                </div>

                <!-- Used to display form errors -->
                <div id="card-errors" role="alert"></div>
                <div><small>Your card information will be stored securely for future use.</small></div>
            </div>
            <?php if (\Lightning\Tools\Configuration::get('modules.stripe.use_plaid')): ?>
                <div class="form-row new-bank">
                    <span id="new-bank-signin" class="button blue medium right">Sign In</span>
                    <p>To make payments using a checking account, sign in to your bank for instant verification using Plaid.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($product->options['subscription'])) : ?>
                <p><small>All payments for this subscription will be made from this account. If you already have a subscription with us, those subscriptions will also be updated to charge the selected account.</small></p>
            <?php endif; ?>

            <div>
                <button class="button red medium right">Submit Payment</button>
                <p><strong>Total amount: <?= $product->printTotalAmount(); ?></strong></p>
            </div>
        </form>
    </div>
</div>
