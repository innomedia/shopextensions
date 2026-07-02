<%-- AJAX mini-cart panel. Rendered server-side into the header (.header__ajaxcart) and
     re-fetched via /ajaxcart/updateCart after every cart mutation. The wrapping element
     always carries data-cart-count so the JS can update the header badge from one source. --%>
<% if $Cart %>
    <% with $Cart %>
        <div class="minicart" data-cart-count="$Items.Quantity">
            <div class="max-h-80 overflow-y-auto -mr-2 pr-2 divide-y divide-primary/10">
                <% loop $Items %>
                    <% if $ShowInTable %>
                        <div class="flex items-start gap-3 py-3" id="minicart-item-{$ID}">
                            <% if $Image %>
                                <a href="$Link" class="shrink-0 block w-12 h-12 rounded bg-grey-light p-1">
                                    <img src="$Image.Pad(96,96,FFFFFF,100).Link" alt="$Title" class="w-full h-full object-contain">
                                </a>
                            <% else %>
                                <span class="shrink-0 flex items-center justify-center w-12 h-12 rounded bg-grey-light text-primary/50">
                                    <i class="bi bi-box-seam"></i>
                                </span>
                            <% end_if %>
                            <div class="flex-1 min-w-0">
                                <a href="$Link" class="block text-sm font-semibold text-primary leading-snug hover:text-accent transition-colors">$TableTitle</a>
                                <span class="block text-xs text-primary/60 mt-0.5">$Quantity &times; $UnitPrice.Nice</span>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="block text-sm font-bold text-primary whitespace-nowrap">$Total.Nice</span>
                                <a href="$removeallLink" data-cart-remove data-cart-item="#minicart-item-{$ID}"
                                   class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-600 transition-colors mt-1"
                                   title="<%t SilverShop\Cart\ShoppingCart.RemoveAllTitle "Remove all of &quot;{Title}&quot; from your cart" Title=$TableTitle %>">
                                    <i class="bi bi-trash"></i> <%t ShopExtensions.Remove "Entfernen" %>
                                </a>
                            </div>
                        </div>
                    <% end_if %>
                <% end_loop %>
            </div>

            <div class="flex items-center justify-between pt-3 mt-1 border-t border-primary/10">
                <span class="text-sm text-primary/70"><%t SilverShop\Model\Order.SubTotal "Zwischensumme" %></span>
                <span class="text-base font-bold text-primary">$SubTotal.Nice</span>
            </div>

            <div class="flex gap-2 mt-4">
                <a href="$Top.CartLink" class="btn-base btn-border-primary btn-sm flex-1 inline-flex items-center justify-center">
                    <%t SilverShop\Cart\ShoppingCart.Cart "Warenkorb" %>
                </a>
                <a href="$Top.CheckoutLink" class="btn-base btn-accent btn-sm flex-1 inline-flex items-center justify-center">
                    <%t SilverShop\Cart\ShoppingCart.Checkout "Kasse" %>
                </a>
            </div>
        </div>
    <% end_with %>
<% else %>
    <div class="minicart py-6 text-center" data-cart-count="0">
        <span class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-grey-light text-primary/40 mb-3">
            <i class="bi bi-cart text-xl"></i>
        </span>
        <p class="text-sm text-primary/60 mb-0"><%t SilverShop\Cart\ShoppingCart.NoItems "Ihr Warenkorb ist leer." %></p>
    </div>
<% end_if %>
