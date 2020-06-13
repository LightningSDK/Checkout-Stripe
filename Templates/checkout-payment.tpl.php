<div class="grid-x">
    <div class="cell small-12 medium-8 large-6 medium-offset-2 large-offset-3">
        <br><br>
        <br><br>
        <h4 class="text-center">Your card will be processed securely:</h4>
        <p class="text-center">
            <img src="/images/checkout/logos/stripe.png" style="height:40px;" />
        </p>
        <form id="payment-form" data-abide="ajax">
            <div class="form-row">
                <label>Email address</label>
                <input type="email" name="email" value="<?= $email; ?>" required>
                <small class="form-error">Please enter a valid email address</small>
            </div>
            <div class="form-row">
                <label for="card-element">
                    Credit or debit card
                </label>
                <div id="card-element">
                    <!-- A Stripe Element will be inserted here. -->
                </div>

                <!-- Used to display form errors. -->
                <div id="card-errors" role="alert"></div>
            </div>
<br><br>
            <div class="text-center">
                <button class="button">Submit Payment</button>
            </div>
        </form>
        <br><br>
        <br><br>
        <br><br>
    </div>
</div>
