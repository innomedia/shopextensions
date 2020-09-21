<div class="container pb-50">
    <div class="row">
        <div class="col-md-12 typography">
            <h1>Bezahlen</h1>
            <% if $SiteConfig.HintStripe %>
                $SiteConfig.HintStripe
            <% end_if %>
            $OrderForm
        </div>
    </div>
</div>


<style>
    #PaymentForm_PaymentForm label {
        display: none;

    }

    #PaymentForm_PaymentForm_stripe{
        padding:9px 12px;
        border: 1px solid #dee2e6 !important;
        border-radius: 0;
        max-width: 550px;
    }
</style>