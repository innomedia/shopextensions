<% if $Status = 'MemberCancelled' %>
    <div class="alert alert-primary" role="alert">
        <%t Order.WASCANCELLED 'Diese Bestellung wurde storniert' %>
    </div>
<% end_if %>

<% if $HasBeenPlaced && $Status = 'Paid' || $HasBeenPlaced && $Status = 'Unpaid' %>
    <div class="alert alert-success" role="alert">
    $SiteConfig.HintAfterPayment
    </div>
<% end_if %>

<% require css("shopextensions/css/order.css") %>
<%-- As Order.ss is also used in emails, avoid div, paragraph and heading elements --%>
<% include SilverShop\Model\Order_Address %>
<% include SilverShop\Model\Order_Content %>
<% if $Total %>
    <% if $Payments %>
        <% include SilverShop\Model\Order_Payments %>
    <% end_if %>
    <table id="OutstandingTable" class="infotable mb-3">
        <tbody>
            <tr class="gap summary" id="Outstanding">
                <th colspan="4" scope="row" class="threeColHeader"><strong><%t SilverShop\Model\Order.TotalOutstanding "Total outstanding" %></strong></th>
                <td class="text-right"><strong>$TotalOutstanding</strong></td>
            </tr>
        </tbody>
    </table>
<% end_if %>
<% if $Notes %>
    <table id="NotesTable" class="infotable mb-20">
        <thead>
            <tr>
                <th><%t SilverShop\Model\Order.db_Notes "Notes" %></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>$Notes</td>
            </tr>
        </tbody>
    </table>
<% end_if %>
<p><a href="javascript:history.back()" class="btn btn-primary">Zurück</a></p>


<%-- if $HasBeenPlaced %>
    <script>
        function waitForFbq(callback){
            if(typeof fbq !== 'undefined'){
                callback()
            } else {
                setTimeout(function () {
                    waitForFbq(callback)
                }, 100)
            }
        }

        waitForFbq(function () {
            fbq('track', 'Purchase',
                    {
                        value: $Total.RAW,
                        currency: '$Currency',
                        contents: [<% loop $Items %>
                            {
                                id: '$Product.InternalItemID',
                                quantity: $Quantity
                            }<% if not $Last %>,<% end_if %>
                        <% end_loop %>
                        ]}
            );
        })
    </script>
<% end_if --%>