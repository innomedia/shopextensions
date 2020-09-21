<% require css("silvershop/core: client/dist/css/cart.css") %>
<% require css("shopextensions/css/cart.css") %>

<div class="container-fluid py-3 py-md-5">
    <div class="row">
        <div class="col-md-12 content py-3 typography">
            <% if $Subtitle %>
                <span class="h5 font_brown">$Subtitle</span>
            <% end_if %>
            <span class="h1 mb-4 font_darkgreen">$Title.RAW</span>
            $Content
            <br/>
            <% if $Cart %>
                <% if $CartFormm %>
                    $CartForm
                <% else %>
                    <% with $Cart %><% include SilverShop\Cart\Cart Editable=true %><% end_with %>
                <% end_if %>
            <% else %>
                <p class="message warning"><%t SilverShop\Cart\ShoppingCart.NoItems "There are no items in your cart." %></p>
            <% end_if %>

            <div class="cartfooter pb-2">
                <% if $Cart %>
                    <% if $CheckoutLink %>
                        <a class="checkoutlink btn btn_round btn-primary mr-2 mb-2" href="<% if $Top.UpsalesPageLink %>$Top.UpsalesPageLink<% else %>$CheckoutLink<% end_if %>">
                            <i class="fal fa-shopping-bag mr-2"></i> <%t SilverShop\Cart\ShoppingCart.ProceedToCheckout 'Proceed to Checkout' %>
                        </a>
                    <% end_if %>
                <% end_if %>

                <% if $ContinueLink %>
                    <a class="continuelink btn btn_round btn-secondary mr-2 mb-2" href="$ContinueLink">
                        <%t SilverShop\Cart\ShoppingCart.ContinueShopping 'Continue Shopping' %>
                    </a>
                <% end_if %>
            </div>
            $Form
        </div>
    </div>
</div>