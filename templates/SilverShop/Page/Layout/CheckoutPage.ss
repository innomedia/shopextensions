<% uncached %>
<% require css("silvershop/core: client/dist/css/checkout.css") %>
<% require css("shopextensions/css/cart.css") %>

<div class="container py-5">
    <div class="row">
        <div class="col-md-12 content py-3 typography">
            <h1>$Title.RAW</h1>

            <% if $PaymentErrorMessage %>
                <p class="message error">
                    <%t SilverShop\Page\CheckoutPage.PaymentErrorMessage 'Received error from payment gateway:' %>
                    $PaymentErrorMessage
                </p>
            <% end_if %>

            $Content

            <% if $Cart %>
                <% with $Cart %>
                    <div class="mb-4 ajaxcartcontainer">
                        <% include SilverShop\Cart\Cart ShowSubtotals=true %>
                    </div>
                <% end_with %>

                <div class="mb-4 d-none">
                    <div class="collapsed relative" id="couponHeadline" data-toggle="collapse" href="#coupon" role="button" aria-expanded="false" aria-controls="coupon">
                        <h2 class="mb-2"><%t CheckoutForm.COUPON 'Rabattcode' %></h2>
                        <p class="mb-2"><%t CouponForm.COUPONHNT 'Hier können Sie Rabattcodes eingeben, wenn Sie diese erhalten haben. ' %></p>
                        <p><span class="btn btn-primary" data-toggle="collapse" href="#coupon" role="button" aria-expanded="false" aria-controls="coupon"><%t CheckoutForm.COUPONHEADING 'Rabattcode einlösen' %></span></p>
                    </div>
                    <div class="collapse pt-1" id="coupon">
                        $CouponForm
                    </div>
                </div>

                $OrderForm
            <% else %>
                <p class="message warning"><%t SilverShop\Cart\ShoppingCart.NoItems "There are no items in your cart." %></p>
            <% end_if %>
        </div>
    </div>
</div>
<% end_uncached %>
<script>
    var openstate = false;
    onDomReady(function () {
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Country').on('change', function () {
            $.ajax({
                type: 'GET',
                url: 'ajaxcart/updateCart?country=' + $(this).val()+ '&'+Math.random(),
                success: function (data, textStatus, request) {
                    $('.ajaxcartcontainer').html(data);
                },
                error: function (request, textStatus, errorThrown) {
                    console.log(request);
                }
            })
        });

        jQuery(".extrabillingtoggle").click(function(){
            openstate = true;
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Company').on('change', function () {
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Company').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Company').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Company').parent().parent().find('label').addClass('active');
            }
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Address').on('change', function () {
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Address').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Address').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Address').parent().parent().find('label').addClass('active');
            }
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_AddressLine2').on('change', function () {
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_AddressLine2').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_AddressLine2').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_AddressLine2').parent().parent().find('label').addClass('active');
            }
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_PostalCode').on('change', function () {
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_PostalCode').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_PostalCode').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_PostalCode').parent().parent().find('label').addClass('active');
            }
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_City').on('change', function () {
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_City').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_City').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_City').parent().parent().find('label').addClass('active');
            }
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Phone').on('change', function () {
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Phone').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Phone').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Phone').parent().parent().find('label').addClass('active');
            }
        });
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_State').on('change', function () {
            if($(this).val() == ''){
                $(this).val(' ');
            }
            if ($('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_State').val() == '' || openstate == false) {
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_State').val($(this).val());
                $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_State').parent().parent().find('label').addClass('active');
            }
        });
        $("#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Country").on('change',function(){
            if($("#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Country").val() == "" || openstate == false)
            {
                $("#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Country").val($("#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Country").val())
            }
        })

        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_State').val(' ');
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_State').val(' ');
        //$('#PaymentForm_OrderForm_SilverShop-Checkout-Component-ShippingAddress_Country').val('DE');
        //$('#PaymentForm_OrderForm_SilverShop-Checkout-Component-BillingAddress_Country').val('DE');

        // Highlight ERRORS
        $('.message.bad').addClass('alert alert-warning');
        // JS Form Validation
        jQuery.extend(jQuery.validator.messages, {
            required: "Dieses Feld muss ausgefüllt werden",
            remote: "Dieses Feld muss ausgefüllt werden",
            email: "Bitte geben Sie eine korrekte Email-Adresse ein",
            url: "Bitte geben Sie eine korrekte URL ein.",
            date: "Bitte geben Sie ein korrektes Datum ein.",
            dateISO: "Bitte geben Sie ein korrektes Datum ein. (ISO).",
            number: "Bitte geben Sie eine Zahl ein.",
            digits: "Bitte geben Sie nur Ziffern ein.",
            creditcard: "Bitte geben Sie eine korrekte Kreditkartennummer ein.",
            equalTo: "gleich",
            accept: "Bitte geben Sie eine Korrekte Endung an",
            maxlength: jQuery.validator.format("Please enter no more than {0} characters."),
            minlength: jQuery.validator.format("Das Passwort ist zu kurz."),
            rangelength: jQuery.validator.format("Please enter a value between {0} and {1} characters long."),
            range: jQuery.validator.format("Please enter a value between {0} and {1}."),
            max: jQuery.validator.format("Please enter a value less than or equal to {0}."),
            min: jQuery.validator.format("Please enter a value greater than or equal to {0}.")
        });

        $("#PaymentForm_OrderForm").validate();
        $("#PaymentForm_ConfirmationForm").validate();
    });
</script>

<style>
    td.subtotal span.h1{
        font-size: 18px !important;
        color:#003087 !important;
        font-family: 'Futura LT W02 Book', sans-serif !important;
        font-weight: normal;
    }
</style>