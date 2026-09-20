<%-- Invoice as a web page (ShopExtensions\Controllers\GuestReceiptController, ?format=html). Unstyled on purpose:
     override ShopExtensions/Layout/GuestReceipt.ss in the theme. Scope: the order. --%>
<article class="guest-receipt">
    <h1 class="guest-receipt__title"><% if $InvoiceNumber %><%t ShopExtensions\Controllers\GuestReceiptController.InvoiceTitle 'Rechnung {number}' number=$InvoiceNumber %><% else %><%t ShopExtensions\Controllers\GuestReceiptController.OrderTitle 'Bestellung {number}' number=$Reference %><% end_if %></h1>
    <dl class="guest-receipt__meta">
        <% if $InvoiceNumber %><dt><%t ShopExtensions\Receipt.InvoiceNumber 'Rechnungsnummer' %></dt><dd>$InvoiceNumber</dd><% end_if %>
        <% if $InvoiceDate %><dt><%t ShopExtensions\Receipt.InvoiceDate 'Rechnungsdatum' %></dt><dd>$InvoiceDate.Format('dd.MM.yyyy')</dd><% end_if %>
        <dt><%t ShopExtensions\Receipt.OrderNumber 'Bestellnummer' %></dt><dd>$Reference</dd>
        <dt><%t ShopExtensions\Receipt.OrderDate 'Bestelldatum' %></dt><dd><% if $Placed %>$Placed.Format('dd.MM.yyyy')<% else %>$Created.Format('dd.MM.yyyy')<% end_if %></dd>
    </dl>
    <% if $InvoicePdfLink %><p><a href="$InvoicePdfLink" type="application/pdf" rel="noreferrer"><%t ShopExtensions\Receipt.Pdf 'Rechnung als PDF herunterladen' %></a></p><% end_if %>
    <section aria-labelledby="guest-receipt-seller"><h2 id="guest-receipt-seller"><%t ShopExtensions\Receipt.Seller 'Rechnungssteller' %></h2>$SiteConfig.ReceiptHeader</section>
    <section aria-labelledby="guest-receipt-billing"><h2 id="guest-receipt-billing"><%t ShopExtensions\Receipt.BillTo 'Rechnungsadresse' %></h2><% with $getBillingAddress %><address>$toString</address><% end_with %></section>
    <section aria-labelledby="guest-receipt-shipping"><h2 id="guest-receipt-shipping"><%t ShopExtensions\Receipt.ShipTo 'Lieferadresse' %></h2><% with $getShippingAddress %><address>$toString</address><% end_with %></section>
    <table class="guest-receipt__items">
        <caption><%t ShopExtensions\Receipt.Items 'Rechnungspositionen' %></caption>
        <thead><tr><th scope="col"><%t ShopExtensions\Receipt.Article 'Artikel' %></th><th scope="col"><%t ShopExtensions\Receipt.Quantity 'Menge' %></th><th scope="col"><%t ShopExtensions\Receipt.UnitPrice 'Einzelpreis' %></th><th scope="col"><%t ShopExtensions\Receipt.LineTotal 'Summe' %></th></tr></thead>
        <tbody>
            <% loop $Items %><tr><th scope="row">$TableTitle<% if $SubTitle %><br>$SubTitle<% end_if %></th><td>$Quantity</td><td>$UnitPrice.Nice</td><td>$Total.Nice</td></tr><% end_loop %>
        </tbody>
        <tfoot>
            <tr><th scope="row" colspan="3"><%t ShopExtensions\Receipt.SubTotal 'Zwischensumme' %></th><td>$SubTotal.Nice</td></tr>
            <% loop $Modifiers %><% if $ShowInTable %><tr><th scope="row" colspan="3">$TableTitle</th><td>$TableValue.Nice</td></tr><% end_if %><% end_loop %>
            <tr><th scope="row" colspan="3"><%t ShopExtensions\Receipt.Total 'Rechnungsbetrag' %></th><td>$Total.Nice</td></tr>
        </tfoot>
    </table>
    <% if $Payments.Count %>
        <section aria-labelledby="guest-receipt-payment"><h2 id="guest-receipt-payment"><%t ShopExtensions\Receipt.Payment 'Zahlung' %></h2>
            <ul><% loop $Payments %><li>$PaymentMethodTitle · $Created.Format('dd.MM.yyyy') · $Amount.Nice · $PaymentStatus</li><% end_loop %></ul>
        </section>
    <% end_if %>
    <% if $SiteConfig.ReceiptFooter %><footer>$SiteConfig.ReceiptFooter</footer><% end_if %>
</article>
