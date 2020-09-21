<script type="text/javascript">
    onDomReady(function () {
        gtag('event', 'purchase', {
            "transaction_id": "$Order.InvoiceNumber()",
            "affiliation": "Zhenobya - Sale",
            "value": $Order.Total().RAW,
            "currency": "$Currency",
            "tax": 0,
            "shipping": 0,
            "items": [
                <% loop $Order.Items %>{
                    "id": "<% if $PreparedProduct.InternalItemID %>$PreparedProduct.InternalItemID<% else %>$PreparedProduct.ID<% end_if %>",
                    "name": "$PreparedProduct.Title",
                    "brand": "$SiteConfig.Title",<%--
                    "category": "",
                    "variant": "",
                    "list_position": 1,--%>
                    "quantity": $Quantity,
                    "price": '$UnitPrice.RAW'
                }<% if not $Last %>,<% end_if %>
            <% end_loop %>
            ]
        });
    });
</script>