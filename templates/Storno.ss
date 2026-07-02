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
                <td style="text-align: right;padding-left: 0.5cm">{$Order.ID}</td>
            </tr>
            <% if $Order.InvoiceNumber %>
                <tr>
                    <td><%t PDFStorno.INVOICENUMBER 'Rechnungsnummer' %></td>
                    <td style="text-align: right;padding-left: 0.5cm">{$Order.InvoiceNumber}</td>
                </tr>
            <% end_if %>
            <% if $Order.InvoiceDate %>
                <tr>
                    <td><%t PDFStorno.RECEIPTDATE 'Rechnungsdatum' %></td>
                    <td style="text-align: right;padding-left: 0.5cm">$Order.InvoiceDate.Format('d.M.Y')</td>
                </tr>
            <% end_if %>
            <tr>
                <td><%t PDFStorno.STORNONUMBER 'Stornonummer' %></td>
                <td style="text-align: right;padding-left: 0.5cm">{$Order.StornoNumber}</td>
            </tr>
            <% if $Order.StornoDate %>
                <tr>
                    <td><%t PDFStorno.STORNODATE 'Stornodatum' %></td>
                    <td style="text-align: right;padding-left: 0.5cm">$Order.StornoDate.Format('d.M.Y')</td>
                </tr>
            <% end_if %>
        </table>
    </div>

    <div class="itemscontainer">
        <h1 style="font-size: 12pt"><%t PDFStorno.STORNO 'Stornorechnung' %> {$Order.StornoNumber}</h1>
        <p style="font-size: 9pt">
            <%t PDFStorno.INTRO 'Stornorechnung zur Rechnung {invoice} — alle Beträge werden gutgeschrieben.' invoice=$Order.InvoiceNumber %>
        </p>
        <% with $Order %>
            <table class="infotable ordercontent" style="width: 100%">
                <colgroup class="product title"/>
                <colgroup class="unitprice" />
                <colgroup class="quantity" />
                <colgroup class="total"/>
                <thead>
                <tr>
                    <th scope="col" style="text-align: left;border-bottom:1pt solid #666;"><%t SilverShop\Page\Product.SINGULARNAME "Product" %></th>
                    <th class="right" scope="col" style="border-bottom:1pt solid #666;"><%t SilverShop\Model\Order.UnitPrice "Unit Price" %></th>
                    <th class="center" scope="col" style="border-bottom:1pt solid #666;"><%t SilverShop\Model\Order.Quantity "Quantity" %></th>
                    <th class="right" scope="col" style="border-bottom:1pt solid #666;"><%t Order.Total "Summe" %></th>
                </tr>
                </thead>

                <tbody>
                    <% loop $Items %>
                    <tr class="itemRow $EvenOdd $FirstLast">
                        <td class="product title" scope="row"<% if $Last %> style="border-bottom:1pt solid #666;"<% end_if %>>
                            <% if $Product(true).ArticleNumber %>
                                Nr. $Product(true).ArticleNumber
                            <% else_if $Product(true).InternalItemID %>
                                Nr. $Product(true).InternalItemID
                            <% end_if %>
                            $TableTitle
                            <% if $SubTitle %>
                                <span class="subtitle">$SubTitle</span>
                            <% end_if %>
                        </td>
                        <td class="right unitprice">$NegUnitPrice</td>
                        <td class="center quantity">$Quantity</td>
                        <td class="right total">$NegTotal</td>
                    </tr>
                    <% end_loop %>
                </tbody>

                <tfoot>
                <tr class="gap summary" id="SubTotal">
                    <td colspan="2" style="border-top:1pt solid #666;"></td>
                    <td colspan="1" scope="row" class="threeColHeader subtotal" style="border-top:1pt solid #666;"><%t SilverShop\Model\Order.SubTotal "Sub-total" %></td>
                    <td class="right" style="border-top:1pt solid #666;">$NegSubTotal</td>
                </tr>
                    <% loop $StornoTaxRows %>
                    <tr class="modifierRow">
                        <td colspan="2"></td>
                        <td colspan="1" scope="row" style="white-space: nowrap">$Label</td>
                        <td class="right" style="white-space: nowrap">$Tax</td>
                    </tr>
                    <% end_loop %>
                <tr class="gap summary total" id="Total">
                    <td colspan="2"></td>
                    <td colspan="1" scope="row" class="threeColHeader total" style="border-top:2pt solid #666;"><%t SilverShop\Model\Order.Total "Total" %></td>
                    <td class="right" style="border-top:2pt solid #666;">$NegTotal</td>
                </tr>
                </tfoot>
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
