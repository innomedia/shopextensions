<% include ReceiptCSS %>
<div class="page">
    <% if $SiteConfig.ReceiptLogoDataURI %><img src="$SiteConfig.ReceiptLogoDataURI" class="logo"/><% end_if %>
    <div class="addresscontainer">
        <div style="padding-left: 0;font-size: 8pt;color:#000000">
            $SiteConfig.ReceiptHeader
        </div>
        <div style="padding-left: 0; padding-top: 0.5cm">
            <% if not $Order.BillingAddress.Name && not $Order.BillingAddress.Member.FirstName %>
            $Order.FirstName $Order.Surname<br/>
            <% end_if %>
            <% with $Order.BillingAddress %>
                <% if $Name %>$Name<br/><% else_if $Member.ID %>$Member.FirstName $Member.Surname<br/><% end_if %>
                $Address<br/>
                <% if $AddressLine2 %>$AddressLine2<br/><% end_if %>
                $PostalCode $City<br/>
                $Country
            <% end_with %>
        </div>
    </div>

    <div class="invoicecontainer">
        <table>
            <tr>
                <td><%t PDFReceipt.ORDERNUMBER 'Bestellnummer' %></td>
                <td style="text-align: right;padding-left: 0.5cm">{$Order.Reference}</td>
            </tr>
            <tr>
                <td><%t PDFReceipt.DATE 'Bestelldatum' %></td>
                <td style="text-align: right;padding-left: 0.5cm">$Order.Created.Format('d.M.Y')</td>
            </tr>
        </table>
    </div>

    <div class="itemscontainer">
        <%-- A delivery slip is not an invoice: title says "Lieferschein", the number is derived
             from the order, and only article + quantity are shown (no prices/totals/payments). --%>
        <h1 style="font-size: 12pt"><%t PDFDeliverySlip.DELIVERYSLIP 'Lieferschein' %> {$Order.Reference}</h1>
        <% with $Order %>
            <table class="infotable ordercontent" style="width: 100%">
                <colgroup class="product title"/>
                <colgroup class="quantity" />
                <thead>
                <tr>
                    <th scope="col" style="text-align: left;border-bottom:1pt solid #666;"><%t SilverShop\Page\Product.SINGULARNAME "Product" %></th>
                    <th class="center" scope="col" style="border-bottom:1pt solid #666;"><%t SilverShop\Model\Order.Quantity "Quantity" %></th>
                </tr>
                </thead>

                <tbody>
                    <% loop $Items %>
                    <tr class="itemRow $EvenOdd $FirstLast">
                        <td class="product title" scope="row"<% if $Last %> style="border-bottom:1pt solid #666;"<% end_if %>>
                            <% if $Link %>
                                <a href="$Link" title="<%t SilverShop\Generic.ReadMoreTitle "Click here to read more on &quot;{Title}&quot;" Title=$TableTitle %>">
                                    $TableTitle
                                    <% if $Product(true).ArticleNumber %>
                                         - Nr. $Product(true).ArticleNumber
                                    <% else_if $Product(true).InternalItemID %>
                                        - Nr. $Product(true).InternalItemID
                                    <% end_if %>
                                </a>
                            <% else %>
                                $TableTitle
                                <% if $Product(true).ArticleNumber %> - Nr. $Product(true).ArticleNumber<% end_if %>
                            <% end_if %>
                            <% if $SubTitle %>
                                <span class="subtitle">$SubTitle</span>
                            <% end_if %>
                        </td>
                        <td class="center quantity">$Quantity</td>
                    </tr>
                    <% end_loop %>
                </tbody>
            </table>
        <% end_with %>
    </div>

    <div class="footer">
        <table style="width: 100%">
            <tr>
                <td style="padding-right: 0.5cm;color:#000000;text-align: center;">
                    $SiteConfig.ReceiptFooter
                </td>
            </tr>
        </table>
    </div>
</div>
