<% require css("innomedia/shopextensions: css/cart.css") %>

<% if $Items %>
    <% loop $Items %>
        <% if $ShowInTable %>
            <div class="row pb-3 py-4 border-top align-items-center">
                <% if $Image %>
                    <div class="col-12 col-md-2 col-xl-1 mb-4 mb-md-0 order-0 order-md-0">
                        <a class="d-block bg-grey rounded p-2 text-center" href="$Link" title="<%t Shop.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$TableTitle %>">
                            <img src="$Image.Pad(120,120,FFFFFF,100).Link" alt="$Title" class="img-fluid cart-image">
                        </a>
                        <span class="badge badge-primary quantity-badge font-14">$Quantity</span>
                    </div>
                <% end_if %>
                <div class="col-12 col-xl-3 d-inline-block d-md-none order-2 order-md-1">
                    <% if $Up.Editable %>
                        <div class="d-inline-block">
                            $QuantityField
                        </div>
                    <% end_if %>

                    <% if $Up.Editable %>
                        <div class="d-inline-block ml-4">
                            <a class="removeall" href="$removeallLink" title="<%t SilverShop\Cart\ShoppingCart.RemoveAllTitle "Remove all of &quot;{Title}&quot; from your cart" Title=$TableTitle %>">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    <% end_if %>
                </div>
                <div class="col-12 col-md-4 col-xl-6 typography order-1 order-md-2">
                    <span class="d-block font_brown h6 font-14">$Product.Parent.Title</span>
                    <h2 class="h4 text-primary mb-3">
                        <% if $Link %>
                            <a href="$Link">$TableTitle</a>
                        <% else %>
                            $TableTitle
                        <% end_if %>
                        <% if $SubTitle %><p class="subtitle">$SubTitle</p><% end_if %>
                    </h2>
                </div>
                <div class="col-12 col-md-4 col-xl-3 order-3 order-md-3">
                    <div class="d-block d-md-none pt-3 typography">
                        <span class="h2 mb-0">$Total.Nice</span>
                        <span class="d-inline-block d-md-block">({$UnitPrice.Nice}/Stück)</span>
                    </div>
                    <% if $Up.Editable %>
                        <div class="d-none d-md-inline-block">
                            $QuantityField
                        </div>
                    <% end_if %>

                    <% if $Up.Editable %>
                        <div class="d-none d-md-inline-block ml-4">
                            <a class="removeall" href="$removeallLink" title="<%t SilverShop\Cart\ShoppingCart.RemoveAllTitle "Remove all of &quot;{Title}&quot; from your cart" Title=$TableTitle %>">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    <% end_if %>
                </div>
                <div class="col-12 col-md-2 d-none d-md-block text-md-right typography order-md-4">
                    <span class="h3 mb-0">$Total.Nice</span>
                    <span class="d-block font_darkgreen cart__unitprice">$UnitPrice.Nice / Stück</span>
                </div>
            </div>
        <% end_if %>
    <% end_loop %>

    <% if $ShowSubtotals %>
        <div class="row border-top py-2">
            <div class="col offset-md-6"><%t SilverShop\Model\Order.SubTotal "Sub-total" %></div>
            <div class="col-auto text-right">$SubTotal.Nice</div>
        </div>

        <% if $Modifiers %>
            <% loop $Modifiers %>
                <% if $ShowInTable %>
                    <div class="row border-top py-2">
                        <div class="col offset-md-6">
                            <% if $Link %>
                                <a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$TableTitle %>">$TableTitle</a>
                            <% else %>
                                $TableTitle.RAW
                            <% end_if %>
                        </div>
                        <div class="col-auto text-right">
                            <% if not Custom %>$TableValue.Nice<% else %>$CustomTableValue.RAW<% end_if %>
                        </div>
                    </div>
                <% end_if %>
            <% end_loop %>
        <% end_if %>


        <div class="row border-top py-2">
            <div class="col offset-md-6 font-weight-bold"><%t SilverShop\Model\Order.Total "Total" %></div>
            <div class="col-auto text-right font-weight-bold">$Total.Nice</div>
        </div>
    <% end_if %>

    <div class="pb-5"></div>

<% else %>
    <p class="message warning">
        <%t SilverShop\Cart\ShoppingCart.NoItems "There are no items in your cart." %>
    </p>

<% end_if %>
