<%-- Order summary returned by POST <checkout>/summary (CheckoutPageControllerExtension, enable_summary_endpoint).
     Scope: the cart order. Unstyled; set CheckoutPageControllerExtension.summary_template to use a project template. --%>
<table class="checkout-summary">
    <tbody>
        <% loop $Items %><tr><th scope="row">$Quantity × $TableTitle</th><td>$Total.Nice</td></tr><% end_loop %>
    </tbody>
    <tfoot>
        <tr><th scope="row"><%t ShopExtensions\Receipt.SubTotal 'Zwischensumme' %></th><td>$SubTotal.Nice</td></tr>
        <% loop $Modifiers %><% if $ShowInTable %><tr><th scope="row">$TableTitle</th><td>$TableValue.Nice</td></tr><% end_if %><% end_loop %>
        <tr><th scope="row"><%t ShopExtensions\Receipt.Total 'Gesamt' %></th><td>$Total.Nice</td></tr>
    </tfoot>
</table>
