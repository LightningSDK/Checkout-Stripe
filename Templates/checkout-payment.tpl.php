<div class="row">
    <div class="column small-12 medium-8 large-6 medium-offset-2 large-offset-3">
        <br><br>
        <br><br>
        <h4 class="text-center">Your card will be processed securely:</h4>
        <br><br>
        <form id="payment-form" data-abide="ajax">
            <div class="form-row">
                <label>Email address</label>
                <input type="email" name="email" required>
                <small class="error">Please enter a valid email address</small>
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
                <button>Submit Payment</button>
            </div>
        </form>
        <br><br>
        <br><br>
        <br><br>
    </div>
</div>
