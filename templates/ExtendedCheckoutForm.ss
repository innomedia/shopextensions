<% if $IncludeFormTag %>
    <form $AttributesHTML>
<% end_if %>
<% if $Message %>
        <p id="{$FormName}_error" class="message $MessageType">$Message</p>
<% else %>
        <p id="{$FormName}_error" class="message $MessageType" style="display: none"></p>
<% end_if %>
    <fieldset>
        <% if $Legend %><legend>$Legend</legend><% end_if %>
        <% if not $CurrentMember %>
            <div class="row py-5">
                <div class="col-md-12">
                    <h2 class="mb-20"><%t CheckoutForm.KUNDENKONTO 'Kundenkonto' %></h2>
                    <p>
                        <%t CheckoutForm.ACCOUNTHINT1 'Damit Sie es zukünftig leichter haben zu bestellen, können Sie hier ein Passwort hinterlegen.' %><br/>
                        <%t CheckoutForm.ACCOUNTHINT2 'Sie haben schon ein Konto?' %>
                        <a href="/Security/Login?BackURL=/checkout/"><%t CheckoutForm.ACCOUNTHINT3 'Sie können Sich hier anmelden' %></a>
                    </p>
                </div>
                <div class="col-md-12">$Fields.fieldByName(SilverShop-Checkout-Component-Membership_Password).FieldHolder</div>
            </div>
        <% end_if %>
        <div class="row pb-3">
            <div class="col-md-12">
                <h2 class="mb-2"><%t CheckoutForm.SHIPPINGADDRESS 'Lieferadresse' %></h2>
            </div>
            <%--
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Company).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_VATNumber).FieldHolder</div>
            --%>

            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-CustomerDetails_FirstName).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-CustomerDetails_Surname).FieldHolder</div>

            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Address).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_AddressLine2).FieldHolder</div>

            <div class="col-md-3">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_PostalCode).FieldHolder</div>
            <div class="col-md-9">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_City).FieldHolder</div>

            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Country).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_State).FieldHolder</div>

            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-CustomerDetails_Email).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Phone).FieldHolder</div>
        </div>
        <div class="row">
            <div class="col-md-12 collapsed extrabillingtoggle" id="billingHeadline" data-toggle="collapse" href="#billingAddress" role="button" aria-expanded="false" aria-controls="billingAddress">
                <h2 class="mb-2">
                    <%t CheckoutForm.BILLINGADDRESS 'Rechnungsadresse' %>
                </h2>
            </div>
            <div class="col-md-12">
                <p class="mb-2"><%t CheckoutForm.BILLINGADDRESSHINT 'Hier können Sie eine abweichende Rechnungsadresse angeben. Klicken Sie auf den Button, wenn die Rechnungsadresse von der Lieferadresse abweicht.' %></p>
                <p><span class="btn btn-primary extrabillingtoggle" data-toggle="collapse" href="#billingAddress" role="button" aria-expanded="false" aria-controls="billingAddress">Abweichende Rechnungsadresse</span></p>
            </div>
        </div>
        <div class="row collapse mb-4 pt-1" id="billingAddress">
            <div class="col-md-12">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Company).FieldHolder</div>

            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Address).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_AddressLine2).FieldHolder</div>

            <div class="col-md-3">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_PostalCode).FieldHolder</div>
            <div class="col-md-9">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_City).FieldHolder</div>

            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Country).FieldHolder</div>
            <div class="col-md-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_State).FieldHolder</div>

            <div class="col-md-12">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Phone).FieldHolder</div>
        </div>

        <div class="row pt-4">
            <div class="col-md-12">
                <h2 class="mb-2"><%t CheckoutForm.PAYMENT 'Bezahlung' %></h2>
                <div class="hintdownpayment">
                    <% if $SiteConfig.HintPayment %>
                        $SiteConfig.HintPayment
                    <% end_if %>
                </div>
                <script>
                    onDomReady(function () {
                        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-Payment_PaymentMethod li').click(function(){
                            if($(this).hasClass('valManual')){
                                $('.hintdownpayment').slideDown();
                            } else {
                                $('.hintdownpayment').slideUp();
                            }
                        });
                    });
                </script>
            </div>
            <div class="col-md-12 checkboxset--inline">$Fields.fieldByName(SilverShop-Checkout-Component-Payment_PaymentMethod).FieldHolder</div>
            <div class="col-md-12">$Fields.fieldByName(SilverShop-Checkout-Component-Notes_Notes).FieldHolder</div>
            <div class="col-md-12">$Fields.fieldByName(SecurityID).FieldHolder</div>
            <div class="clear"><!-- --></div>
        </div>
        <div class="row pb-3">
            <div class="col-md-12">
                <div class="col-md-12">$Fields.fieldByName(SilverShop-Checkout-Component-Terms_ReadTermsAndConditions).FieldHolder</div>
                <style>
                    #PaymentForm_OrderForm_SilverShop-Checkout-Component-Terms_ReadTermsAndConditions_Holder label{
                        float:none !important;
                        margin:0 !important;
                    }

                    #PaymentForm_OrderForm_SilverShop-Checkout-Component-Terms_ReadTermsAndConditions{
                        display: block !important;
                        opacity: 0 !important;
                        position: absolute;
                    }
                </style>
            </div>
        </div>
    </fieldset>

<% if $Actions %>
        <div class="btn-toolbar">
            <% loop $Actions %>
                $Field
            <% end_loop %>
        </div>
<% end_if %>
<% if $IncludeFormTag %>
    </form>
<% end_if %>

<script>
    onDomReady(function(){
        $('ul.optionset label').on('click', function(){
            $(this).closest('ul').find('label').removeClass('selected');
            $(this).toggleClass('selected');
        });


        function scrollToInvalid(form) {

            // listen for `invalid` events on all form inputs
            form.find(':input').on('invalid', function(event) {

                $('html,body').delay(50).animate({
                    scrollTop: '-=100px'
                }, 50);
            });
        }
        // call it like this
        var form = $('#PaymentForm_OrderForm')   //your form element
        scrollToInvalid(form);

    });
</script>