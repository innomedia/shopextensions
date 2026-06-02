<% if $IncludeFormTag %>
    <form $AttributesHTML>
<% end_if %>
<% if $Message %>
        <div id="{$FormName}_error" class="bg-yellow-50 border border-yellow-200 rounded-md lg:rounded-lg p-4 lg:p-5 mb-6 text-primary">$Message</div>
<% else %>
        <div id="{$FormName}_error" class="hidden bg-yellow-50 border border-yellow-200 rounded-md lg:rounded-lg p-4 lg:p-5 mb-6 text-primary"></div>
<% end_if %>
    <fieldset class="border-0 p-0">
        <% if $Legend %><legend class="text-2xl font-bold text-primary mb-4">$Legend</legend><% end_if %>
        
        <%-- Account Creation Section --%>
        <% if not $CurrentMember %>
            <div class="mb-8 lg:mb-10 pb-8 lg:pb-10 border-b border-primary/10">
                <h2 class="text-xl lg:text-2xl font-bold text-primary mb-4 lg:mb-5"><%t CheckoutForm.KUNDENKONTO 'Kundenkonto' %></h2>
                <p class="text-primary/70 mb-4 lg:mb-5">
                    <%t CheckoutForm.ACCOUNTHINT1 'Damit Sie es zukünftig leichter haben zu bestellen, können Sie hier ein Passwort hinterlegen.' %><br/>
                    <%t CheckoutForm.ACCOUNTHINT2 'Sie haben schon ein Konto?' %>
                    <a href="/Security/Login?BackURL=/checkout/" class="text-accent hover:underline"><%t CheckoutForm.ACCOUNTHINT3 'Sie können Sich hier anmelden' %></a>
                </p>
                <div>$Fields.fieldByName(SilverShop-Checkout-Component-Membership_Password).FieldHolder</div>
            </div>
        <% end_if %>
        
        <%-- Customer Details Section --%>
        <div class="mb-8 lg:mb-10">
            <h2 class="text-xl lg:text-2xl font-bold text-primary mb-4 lg:mb-5"><%t CheckoutForm.CUSTOMERDETAILS 'Kontaktdaten' %></h2>
            <div class="grid grid-cols-12 gap-4 lg:gap-6">
                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-CustomerDetails_FirstName).FieldHolder</div>
                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-CustomerDetails_Surname).FieldHolder</div>
                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-CustomerDetails_Email).FieldHolder</div>
            </div>
        </div>

        <%-- Shipping Address Section (conditional) --%>
        <% if $Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Address) %>
        <div class="mb-8 lg:mb-10">
            <h2 class="text-xl lg:text-2xl font-bold text-primary mb-4 lg:mb-5"><%t CheckoutForm.SHIPPINGADDRESS 'Lieferadresse' %></h2>
            <div class="grid grid-cols-12 gap-4 lg:gap-6">
                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Address).FieldHolder</div>
                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_AddressLine2).FieldHolder</div>

                <div class="col-span-12 md:col-span-3">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_PostalCode).FieldHolder</div>
                <div class="col-span-12 md:col-span-9">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_City).FieldHolder</div>

                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Country).FieldHolder</div>
                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_State).FieldHolder</div>

                <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Phone).FieldHolder</div>
            </div>
        </div>
        <% end_if %>

        <%-- Billing Address Section --%>
        <div class="mb-8 lg:mb-10">
            <h2 class="text-xl lg:text-2xl font-bold text-primary mb-4 lg:mb-5">
                <%t CheckoutForm.BILLINGADDRESS 'Rechnungsadresse' %>
            </h2>
            <div class="mb-4 lg:mb-5">
                <% if $Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Address) %>
                    <p class="text-primary/70 mb-4"><%t CheckoutForm.BILLINGADDRESSHINT 'Hier können Sie eine abweichende Rechnungsadresse angeben. Klicken Sie auf den Button, wenn die Rechnungsadresse von der Lieferadresse abweicht.' %></p>
                    <button type="button" class="btn-base btn-accent extrabillingtoggle" onclick="document.getElementById('billingAddress').classList.toggle('hidden')">
                        Abweichende Rechnungsadresse
                    </button>
                <% else %>
                    <p class="text-primary/70 mb-4"><%t CheckoutForm.BILLINGADDRESSONLY 'Bitte geben Sie Ihre Rechnungsadresse an.' %></p>
                <% end_if %>
            </div>
            <div class="<% if $Fields.fieldByName(SilverShop-Checkout-Component-ShippingAddress_Address) %>hidden<% end_if %>" id="billingAddress">
                <div class="grid grid-cols-12 gap-4 lg:gap-6">
                    <div class="col-span-12">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Company).FieldHolder</div>

                    <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Address).FieldHolder</div>
                    <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_AddressLine2).FieldHolder</div>

                    <div class="col-span-12 md:col-span-3">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_PostalCode).FieldHolder</div>
                    <div class="col-span-12 md:col-span-9">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_City).FieldHolder</div>

                    <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Country).FieldHolder</div>
                    <div class="col-span-12 md:col-span-6">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_State).FieldHolder</div>

                    <div class="col-span-12">$Fields.fieldByName(SilverShop-Checkout-Component-BillingAddress_Phone).FieldHolder</div>
                </div>
            </div>
        </div>

        <%-- Payment Section --%>
        <div class="mb-8 lg:mb-10">
            <h2 class="text-xl lg:text-2xl font-bold text-primary mb-4 lg:mb-5"><%t CheckoutForm.PAYMENT 'Bezahlung' %></h2>
            <div class="hintdownpayment hidden bg-blue-50 border border-blue-200 rounded-md lg:rounded-lg p-4 lg:p-5 mb-4 text-primary">
                <% if $SiteConfig.HintPayment %>
                    $SiteConfig.HintPayment
                <% end_if %>
            </div>
            <div class="mb-4 lg:mb-5 checkboxset--inline">$Fields.fieldByName(SilverShop-Checkout-Component-Payment_PaymentMethod).FieldHolder</div>
            <div class="mb-4 lg:mb-5">$Fields.fieldByName(SilverShop-Checkout-Component-Notes_Notes).FieldHolder</div>
            <div class="hidden">$Fields.fieldByName(SecurityID).FieldHolder</div>
        </div>

        <%-- Terms & Conditions --%>
        <div class="mb-8 lg:mb-10">
            <div>$Fields.fieldByName(SilverShop-Checkout-Component-Terms_ReadTermsAndConditions).FieldHolder</div>
        </div>
    </fieldset>

<% if $Actions %>
        <div class="flex flex-wrap gap-3 lg:gap-4">
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
        // Payment method hint toggle
        $('#PaymentForm_OrderForm_SilverShop-Checkout-Component-Payment_PaymentMethod li').click(function(){
            if($(this).hasClass('valManual')){
                $('.hintdownpayment').removeClass('hidden').hide().slideDown();
            } else {
                $('.hintdownpayment').slideUp(function() {
                    $(this).addClass('hidden');
                });
            }
        });

        // Highlight selected option
        $('ul.optionset label').on('click', function(){
            $(this).closest('ul').find('label').removeClass('selected');
            $(this).toggleClass('selected');
        });

        // Scroll to invalid field on form validation error
        function scrollToInvalid(form) {
            form.find(':input').on('invalid', function(event) {
                $('html,body').delay(50).animate({
                    scrollTop: '-=100px'
                }, 50);
            });
        }
        
        var form = $('#PaymentForm_OrderForm');
        if (form.length) {
            scrollToInvalid(form);
        }
    });
</script>

<style>
    /* Hide the actual checkbox but keep it functional for validation */
    #PaymentForm_OrderForm_SilverShop-Checkout-Component-Terms_ReadTermsAndConditions {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    
    #PaymentForm_OrderForm_SilverShop-Checkout-Component-Terms_ReadTermsAndConditions_Holder label {
        cursor: pointer;
    }
</style>