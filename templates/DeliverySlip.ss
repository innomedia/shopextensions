<% include ReceiptCSS %>
<div class="page">
    <img src="$SiteConfig.ReceiptLogo.Fit(770,770).Link" class="logo"/>
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

    <%--
    <div class="shippingcontainer">
        <strong><%t SilverShop\Model\Order.ShipTo "Ship To" %></strong><br/>
        <% if not $Order.BillingAddress.Name && not $Order.BillingAddress.Member.FirstName %>
            $Order.FirstName $Order.Surname<br/>
        <% end_if %>
        <% with $Order.ShippingAddress %>
            <% if $Name %>$Name<br/><% else_if $Member.ID %>$Member.FirstName $Member.Surname<br/><% end_if %>
            $Address<br/>
            <% if $AddressLine2 %>$AddressLine2<br/><% end_if %>
            $PostalCode $City<br/>
            $Country
        <% end_with %>
    </div>
    --%>

    <div class="invoicecontainer">
        <table>
            <tr>
                <td><%t PDFReceipt.ORDERNUMBER 'Bestellnummer' %></td>
                <td style="text-align: right;padding-left: 0.5cm">{$Order.ID}</td>
            </tr>
            <tr>
                <td><%t PDFReceipt.DATE 'Bestelldatum' %></td>
                <td style="text-align: right;padding-left: 0.5cm">$Order.Created.Format('d.M.Y')</td>
            </tr>
            <% if $Order.InvoiceNumber %>
                <tr>
                    <td><%t PDFReceipt.INVOICENUMBER 'Rechnungsnummer' %></td>
                    <td style="text-align: right;padding-left: 0.5cm">{$Order.InvoiceNumber}</td>
                </tr>
            <% end_if %>

            <% if $Order.InvoiceDate %>
                <tr>
                    <td><%t PDFReceipt.RECEIPTDATE 'Rechnungsdatum' %></td>
                    <td style="text-align: right;padding-left: 0.5cm">$Order.InvoiceDate.Format('d.M.Y')</td>
                </tr>
            <% end_if %>
        </table>
    </div>

    <div class="itemscontainer">
        <% if $Order.InvoiceNumber %>
            <h1 style="font-size: 12pt"><%t PDFReceipt.RECEIPT 'Rechnung' %> {$Order.InvoiceNumber}</h1>
        <% else %>
            <h1 style="font-size: 12pt"><%t PDFReceipt.ORDER 'Bestellung' %> {$Order.ID}</h1>
        <% end_if %>
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
                                - Nr. $Product(true).ArticleNumber $TableTitle
                            <% end_if %>
                            <% if $SubTitle %>
                                <span class="subtitle">$SubTitle</span>
                            <% end_if %>
                        </td>
                        <td class="right unitprice">$UnitPrice</td>
                        <td class="center quantity">$Quantity</td>
                        <td class="right total">$Total</td>
                    </tr>
                    <% end_loop %>
                </tbody>

                <tfoot>
                <tr class="gap summary" id="SubTotal">
                    <td colspan="2" style="border-top:1pt solid #666;"></td>
                    <td colspan="1" scope="row" class="threeColHeader subtotal" style="border-top:1pt solid #666;"><%t SilverShop\Model\Order.SubTotal "Sub-total" %></td>
                    <td class="right" style="border-top:1pt solid #666;">$SubTotal.Nice</td>
                </tr>
                    <% loop $Modifiers %>
                        <% if $ShowInTable %>
                        <tr class="modifierRow $EvenOdd $FirstLast $Classes">
                            <td colspan="2"></td>
                            <td colspan="1" scope="row" style="white-space: nowrap">
                                <% if $TableTitle = 'Discount' %>
                                    <%t Order.DISCOUNT 'Discount' %>
                                <% else %>
                                    <% if not Custom %>$TableTitle<% else %>$TableTitle.RAW<% end_if %>
                                <% end_if %>
                            </td>
                            <td class="right" style="white-space: nowrap"><% if not Custom %>$TableValue.Nice<% else %>$CustomTableValue.RAW<% end_if %></td>
                        </tr>
                        <% end_if %>
                    <% end_loop %>
                <tr class="gap summary total" id="Total">
                    <td colspan="2"></td>
                    <td colspan="1" scope="row" class="threeColHeader total" style="border-top:2pt solid #666;"><%t SilverShop\Model\Order.Total "Total" %></td>
                    <td class="right" style="border-top:2pt solid #666;">$Total</td>
                </tr>
                </tfoot>

            </table>

            <table id="PaymentTable" class="infotable" style="width: 100%; margin-top:1cm">
                <thead>
                <tr class="gap mainHeader">
                    <th colspan="4" class="left" style="border-bottom:1pt solid #666;"><%t SilverShop\Payment.PaymentsHeadline "Payment(s)" %></th>
                </tr>
                <tr>
                    <th scope="row" class="twoColHeader" style="border-bottom:1pt solid #666;"><%t SilverStripe\Omnipay\Model\Payment.Date "Date" %></th>
                    <th scope="row" class="twoColHeader" style="border-bottom:1pt solid #666;"><%t SilverStripe\Omnipay\Model\Payment.Amount "Amount" %></th>
                    <th scope="row" class="twoColHeader" style="border-bottom:1pt solid #666;"><%t SilverStripe\Omnipay\Model\Payment.db_Status "Payment Status" %></th>
                    <th scope="row" class="twoColHeader" style="border-bottom:1pt solid #666;"><%t SilverStripe\Omnipay\Model\Payment.db_Gateway "Method" %></th>
                </tr>
                </thead>
                <tbody>
                    <% loop $Payments %>
                    <tr>
                        <td class="price">$Created.Nice</td>
                        <td class="price">$Amount.Nice $Currency</td>
                        <td class="price">$PaymentStatus</td>
                        <td class="price">$GatewayTitle</td>
                    </tr>
                        <% if $ShowMessages %>
                            <% loop $Messages %>
                            <tr>
                                <td colspan="4">
                                    $ClassName $Message $User.Name
                                </td>
                            </tr>
                            <% end_loop %>
                        <% end_if %>
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