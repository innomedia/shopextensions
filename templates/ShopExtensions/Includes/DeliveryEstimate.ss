<%-- Expected delivery date (ShopExtensions\Delivery\DeliveryEstimate). Scope: a controller/object with $DeliveryEstimate. Unstyled. --%>
<% with $DeliveryEstimate %>
    <p class="delivery-estimate"><%t ShopExtensions\Delivery\DeliveryEstimate.ArrivesOn 'Voraussichtliche Lieferung am' %> <time datetime="$DeliveryDateISO">$DeliveryDateNice</time></p>
<% end_with %>
