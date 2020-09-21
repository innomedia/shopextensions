<%-- require themedCSS('components/_sidecart') --%>
<% require css("silvershop/core: client/dist/css/sidecart.css") %>

<div class="sidecart typography">
    <% if $Cart %>
        <% with $Cart %>
            <p class="itemcount mb-3">
                <% if $Items.Plural %>
                    <%t SilverShop\Cart\ShoppingCart.ItemsInCartPlural 'There are <a href="{link}">{quantity} items</a> in your cart.' link=$Top.CartLink quantity=$Items.Quantity %>
                <% else %>
                    <%t SilverShop\Cart\ShoppingCart.ItemsInCartSingular 'There is <a href="{link}">1 item</a> in your cart.' link=$Top.CartLink %>
                <% end_if %>
            </p>
            <table>
                <% loop $Items %>
                    <tr class="item $EvenOdd $FirstLast <% if not $First %>border-top<% end_if %> py-3 bg-white" id="sidecart__item--{$ID}">
                        <td class="title">
                            <a href="$Product.Link" class="p-0 pr-4">
                                $TableTitle
                            </a>
                        </td>
                        <td class="text-right">
                            <a class="remove ajax btn btn-sm btn-grey" href="$removeallLink" data-remove="#sidecart__item--{$ID}"><i class="fal fa-trash"></i></a>
                        </td>
                    </tr>
                <% end_loop %>
            </table>

            <div class="checkout clearfix d-block pt-2" style="clear: both">
                <a href="$Top.CheckoutLink" class="btn btn_round btn-primary mr-3"><%t SilverShop\Cart\ShoppingCart.Checkout "Checkout" %></a>
                <a href="$Top.CartLink" class="btn btn_round btn-secondary"><%t SilverShop\Cart\ShoppingCart.Cart "Warenkorb" %></a>
            </div>
        <% end_with %>
    <% else %>
        <p class="noItems"><%t SilverShop\Cart\ShoppingCart.NoItems "There are no items in your cart." %></p>
    <% end_if %>
</div>
