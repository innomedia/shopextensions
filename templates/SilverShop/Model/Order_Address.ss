<p>Bestellung von: $getLatestEmail</p>
<table id="ShippingTable" class="infotable">
    <thead>
    <tr>
        <th><%t SilverShop\Model\Order.ShipTo "Ship To" %></th>
        <th><%t SilverShop\Model\Order.BillTo "Bill To" %></th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            <% with $getShippingAddress %>
                <% if $Name %>$Name<br/><% end_if %>
                <% if $Company %>$Company<br/><% end_if %>
                <% if $Address %>$Address<br/><% end_if %>
                <% if $AddressLine2 %>$AddressLine2<br/><% end_if %>
                <% if $PostalCode %>$PostalCode<br/><% end_if %>
                <% if $City %>$City<br/><% end_if %>
                <% if $State %>$State<br/><% end_if %>
                <% if $Country %>$Country<br/><% end_if %>
                <% if $Phone %>$Phone<% end_if %>
            <% end_with %>
        </td>
        <td>
            <% with $getBillingAddress %>
                <% if $Name %>$Name<br/><% end_if %>
                <% if $Company %>$Company<br/><% end_if %>
                <% if $Address %>$Address<br/><% end_if %>
                <% if $AddressLine2 %>$AddressLine2<br/><% end_if %>
                <% if $PostalCode %>$PostalCode<br/><% end_if %>
                <% if $City %>$City<br/><% end_if %>
                <% if $State %>$State<br/><% end_if %>
                <% if $Country %>$Country<br/><% end_if %>
                <% if $Phone %>$Phone<% end_if %>
            <% end_with %>
        </td>
    </tr>
    </tbody>
</table>