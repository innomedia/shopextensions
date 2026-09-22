<%-- Error list for the checkout form. Include with $CheckoutErrorSummary in scope (CheckoutPageControllerExtension). Unstyled. --%>
<% if $CheckoutErrorSummary.Count %>
    <div class="checkout-error-summary" role="alert" tabindex="-1">
        <p><%t ShopExtensions\CheckoutPageControllerExtension.ErrorsTitle 'Bitte prüfen Sie Ihre Angaben:' %></p>
        <ul><% loop $CheckoutErrorSummary %><li><a href="#$ID">$Title: $Message</a></li><% end_loop %></ul>
    </div>
<% end_if %>
<%-- if first: <% with %> renders its block even when the value is null (empty notice). --%>
<% if $CheckoutPaymentNotice %><% with $CheckoutPaymentNotice %>
    <div class="checkout-payment-notice" role="alert"><p><strong>$Title</strong> $Text</p></div>
<% end_with %><% end_if %>
